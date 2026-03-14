import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

type Option = {
    value: string;
    label: string;
    group?: string;
};

type SearchableSelectProps = {
    options: Option[];
    value: string | null;
    onValueChange: (value: string | null) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    /** If provided, adds this as the first option (e.g. "All accounts") that maps to null value */
    allOption?: string;
    className?: string;
};

export function SearchableSelect({
    options,
    value,
    onValueChange,
    placeholder = 'Select...',
    searchPlaceholder = 'Search...',
    emptyMessage = 'No results found.',
    allOption,
    className,
}: SearchableSelectProps) {
    const [open, setOpen] = useState(false);

    const selectedLabel = value
        ? options.find((o) => o.value === value)?.label
        : (allOption ?? placeholder);

    const hasGroups = options.some((o) => o.group);
    const grouped = hasGroups
        ? options.reduce<Record<string, Option[]>>((acc, opt) => {
              const group = opt.group ?? '';
              if (!acc[group]) acc[group] = [];
              acc[group].push(opt);
              return acc;
          }, {})
        : null;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className={cn(
                        'w-full justify-between font-normal',
                        !value && 'text-muted-foreground',
                        className,
                    )}
                >
                    <span className="truncate">{selectedLabel}</span>
                    <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-[--radix-popover-trigger-width] p-0"
                align="start"
            >
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyMessage}</CommandEmpty>
                        {allOption && (
                            <CommandGroup>
                                <CommandItem
                                    value={allOption}
                                    onSelect={() => {
                                        onValueChange(null);
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 size-4',
                                            value === null
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    {allOption}
                                </CommandItem>
                            </CommandGroup>
                        )}
                        {grouped ? (
                            Object.entries(grouped).map(
                                ([group, groupOptions]) => (
                                    <CommandGroup
                                        key={group}
                                        heading={group || undefined}
                                    >
                                        {groupOptions.map((option) => (
                                            <CommandItem
                                                key={option.value}
                                                value={option.label}
                                                onSelect={() => {
                                                    onValueChange(
                                                        option.value === value
                                                            ? null
                                                            : option.value,
                                                    );
                                                    setOpen(false);
                                                }}
                                            >
                                                <Check
                                                    className={cn(
                                                        'mr-2 size-4',
                                                        value === option.value
                                                            ? 'opacity-100'
                                                            : 'opacity-0',
                                                    )}
                                                />
                                                {option.label}
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                ),
                            )
                        ) : (
                            <CommandGroup>
                                {options.map((option) => (
                                    <CommandItem
                                        key={option.value}
                                        value={option.label}
                                        onSelect={() => {
                                            onValueChange(
                                                option.value === value
                                                    ? null
                                                    : option.value,
                                            );
                                            setOpen(false);
                                        }}
                                    >
                                        <Check
                                            className={cn(
                                                'mr-2 size-4',
                                                value === option.value
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        {option.label}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
