import { format, parse } from 'date-fns';
import { CalendarIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

type Preset = {
    key: string;
    label: string;
};

type ReportDateRangePickerProps = {
    from: string;
    to: string;
    preset: string;
    presets: Preset[];
    compareEnabled: boolean;
    compareFrom?: string;
    compareTo?: string;
    onRangeChange: (range: {
        from: string;
        to: string;
        preset: string;
    }) => void;
    onPresetSelect: (presetKey: string) => void;
    onCompareToggle: (enabled: boolean) => void;
    onCompareRangeChange: (range: { from: string; to: string }) => void;
    className?: string;
};

function parseDate(value?: string): Date | undefined {
    if (!value) {
        return undefined;
    }

    const normalized = value.slice(0, 10);
    const parsed = parse(normalized, 'yyyy-MM-dd', new Date());

    if (isNaN(parsed.getTime())) {
        return undefined;
    }

    return parsed;
}

function formatDateStr(d: Date): string {
    return format(d, 'yyyy-MM-dd');
}

function formatDisplay(d: Date): string {
    return format(d, 'MMM d, yyyy');
}

export function ReportDateRangePicker({
    from,
    to,
    preset,
    presets,
    compareEnabled,
    compareFrom,
    compareTo,
    onRangeChange,
    onPresetSelect,
    onCompareToggle,
    onCompareRangeChange,
    className,
}: ReportDateRangePickerProps) {
    const [open, setOpen] = useState(false);

    const selected: DateRange | undefined = useMemo(() => {
        const fromDate = parseDate(from);
        const toDate = parseDate(to);

        if (!fromDate && !toDate) {
            return undefined;
        }

        return { from: fromDate, to: toDate };
    }, [from, to]);

    const compareSelected: DateRange | undefined = useMemo(() => {
        const fromDate = parseDate(compareFrom);
        const toDate = parseDate(compareTo);

        if (!fromDate && !toDate) {
            return undefined;
        }

        return { from: fromDate, to: toDate };
    }, [compareFrom, compareTo]);

    const handleSelect = useCallback(
        (range: DateRange | undefined) => {
            const newFrom = range?.from ? formatDateStr(range.from) : '';
            const newTo = range?.to ? formatDateStr(range.to) : '';

            if (newFrom && newTo) {
                onRangeChange({ from: newFrom, to: newTo, preset: 'custom' });
            }
        },
        [onRangeChange],
    );

    const handleCompareSelect = useCallback(
        (range: DateRange | undefined) => {
            const newFrom = range?.from ? formatDateStr(range.from) : '';
            const newTo = range?.to ? formatDateStr(range.to) : '';

            if (newFrom && newTo) {
                onCompareRangeChange({ from: newFrom, to: newTo });
            }
        },
        [onCompareRangeChange],
    );

    const handlePresetClick = useCallback(
        (presetKey: string) => {
            onPresetSelect(presetKey);
            setOpen(false);
        },
        [onPresetSelect],
    );

    // Suggest previous period when compare is toggled on
    const suggestPreviousPeriod = useCallback(() => {
        const fromDate = parseDate(from);
        const toDate = parseDate(to);

        if (!fromDate || !toDate) {
            return;
        }

        const durationMs = toDate.getTime() - fromDate.getTime();
        const prevEnd = new Date(fromDate.getTime() - 86400000);
        const prevStart = new Date(prevEnd.getTime() - durationMs);
        onCompareRangeChange({
            from: formatDateStr(prevStart),
            to: formatDateStr(prevEnd),
        });
    }, [from, to, onCompareRangeChange]);

    // Auto-suggest when compare is first enabled
    useEffect(() => {
        if (compareEnabled && !compareFrom && !compareTo) {
            suggestPreviousPeriod();
        }
    }, [compareEnabled, compareFrom, compareTo, suggestPreviousPeriod]);

    const displayText = useMemo(() => {
        const fromDate = parseDate(from);
        const toDate = parseDate(to);

        if (fromDate && toDate) {
            return `${formatDisplay(fromDate)} – ${formatDisplay(toDate)}`;
        }

        if (fromDate) {
            return `${formatDisplay(fromDate)} – ...`;
        }

        return 'Select date range';
    }, [from, to]);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    className={cn(
                        'justify-start text-left font-normal',
                        className,
                    )}
                >
                    <CalendarIcon className="size-4 shrink-0" />
                    <span className="truncate">{displayText}</span>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start" sideOffset={4}>
                <div className="flex flex-col sm:flex-row">
                    {/* Presets sidebar */}
                    <div className="flex flex-row gap-1 border-b p-2 sm:flex-col sm:border-r sm:border-b-0">
                        {presets.map((p) => (
                            <button
                                key={p.key}
                                type="button"
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-left text-sm whitespace-nowrap transition-colors',
                                    preset === p.key
                                        ? 'bg-primary text-primary-foreground'
                                        : 'hover:bg-accent',
                                )}
                                onClick={() => handlePresetClick(p.key)}
                            >
                                {p.label}
                            </button>
                        ))}
                    </div>

                    {/* Calendar + compare */}
                    <div className="flex flex-col">
                        <Calendar
                            mode="range"
                            selected={selected}
                            onSelect={handleSelect}
                            defaultMonth={parseDate(from)}
                            numberOfMonths={2}
                            captionLayout="dropdown"
                            startMonth={
                                new Date(new Date().getFullYear() - 10, 0)
                            }
                            endMonth={
                                new Date(new Date().getFullYear() + 10, 0)
                            }
                        />

                        {/* Compare section */}
                        <div className="border-t px-3 py-2.5">
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="compare-toggle"
                                    checked={compareEnabled}
                                    onCheckedChange={(checked) =>
                                        onCompareToggle(checked === true)
                                    }
                                />
                                <Label
                                    htmlFor="compare-toggle"
                                    className="text-sm font-normal"
                                >
                                    Compare with previous period
                                </Label>
                            </div>

                            {compareEnabled && (
                                <div className="mt-2 space-y-2">
                                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <span>
                                            {compareFrom && compareTo
                                                ? `${formatDisplay(parseDate(compareFrom)!)} – ${formatDisplay(parseDate(compareTo)!)}`
                                                : 'Select comparison range'}
                                        </span>
                                        <button
                                            type="button"
                                            className="text-xs text-primary underline-offset-2 hover:underline"
                                            onClick={suggestPreviousPeriod}
                                        >
                                            Auto-fill
                                        </button>
                                    </div>
                                    <Calendar
                                        mode="range"
                                        selected={compareSelected}
                                        onSelect={handleCompareSelect}
                                        defaultMonth={
                                            parseDate(compareFrom) ??
                                            parseDate(from)
                                        }
                                        numberOfMonths={2}
                                        captionLayout="dropdown"
                                        startMonth={
                                            new Date(
                                                new Date().getFullYear() - 10,
                                                0,
                                            )
                                        }
                                        endMonth={
                                            new Date(
                                                new Date().getFullYear() + 10,
                                                0,
                                            )
                                        }
                                    />
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
