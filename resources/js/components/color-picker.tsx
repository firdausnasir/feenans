import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

const PRESET_COLORS = [
    '#ef4444',
    '#f97316',
    '#f59e0b',
    '#eab308',
    '#84cc16',
    '#22c55e',
    '#14b8a6',
    '#06b6d4',
    '#3b82f6',
    '#6366f1',
    '#8b5cf6',
    '#a855f7',
    '#d946ef',
    '#ec4899',
    '#f43f5e',
    '#6b7280',
    '#78716c',
    '#64748b',
    '#0ea5e9',
    '#10b981',
] as const;

type ColorPickerProps = {
    readonly value: string;
    readonly onChange: (color: string) => void;
    readonly id?: string;
    readonly className?: string;
};

export function ColorPicker({
    value,
    onChange,
    id,
    className,
}: ColorPickerProps) {
    const [open, setOpen] = useState(false);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    className={cn('h-7 w-8 p-0.5', className)}
                    title="Pick color"
                >
                    <span
                        className="h-full w-full rounded-sm"
                        style={{ backgroundColor: value }}
                    />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-3" align="start">
                <div className="space-y-3">
                    <div className="grid grid-cols-5 gap-1.5">
                        {PRESET_COLORS.map((color) => (
                            <button
                                key={color}
                                type="button"
                                className={cn(
                                    'h-6 w-6 rounded-md border transition-transform hover:scale-110',
                                    value === color
                                        ? 'border-foreground ring-2 ring-foreground/20'
                                        : 'border-border',
                                )}
                                style={{ backgroundColor: color }}
                                onClick={() => {
                                    onChange(color);
                                    setOpen(false);
                                }}
                                title={color}
                            />
                        ))}
                    </div>
                    <div className="flex items-center gap-2">
                        <Label htmlFor="custom-color" className="sr-only">
                            Custom color
                        </Label>
                        <Input
                            id="custom-color"
                            type="color"
                            value={value}
                            onChange={(e) => onChange(e.target.value)}
                            className="h-7 w-8 cursor-pointer border-border bg-transparent p-0.5"
                        />
                        <Input
                            value={value}
                            onChange={(e) => {
                                const hex = e.target.value;

                                if (/^#[0-9a-fA-F]{0,6}$/.test(hex)) {
                                    onChange(hex);
                                }
                            }}
                            onBlur={() => {
                                if (!/^#[0-9a-fA-F]{6}$/.test(value)) {
                                    onChange('#6b7280');
                                }
                            }}
                            className="h-7 w-20 font-mono text-xs"
                            placeholder="#000000"
                        />
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
