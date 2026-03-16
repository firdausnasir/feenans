import { Check, ChevronsUpDown, PlusCircle } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
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
    color?: string | null;
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
    /** When true, shows a "Create '[search]'" option when the search text has no exact match */
    creatable?: boolean;
    /** Called with the typed name when the user picks the "Create" option */
    onCreate?: (name: string) => void;
    /** Label shown for the create entry, receives the search text. Defaults to "Create '[name]'" */
    createLabel?: string;
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
    creatable = false,
    onCreate,
    createLabel,
}: SearchableSelectProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const selectedOption = value
        ? options.find((o) => o.value === value)
        : null;
    const selectedLabel = value
        ? (selectedOption?.label ?? (createLabel || value))
        : (allOption ?? placeholder);
    const selectedColor = selectedOption?.color ?? null;

    const hasGroups = options.some((o) => o.group);
    const grouped = hasGroups
        ? options.reduce<Record<string, Option[]>>((acc, opt) => {
              const group = opt.group ?? '';

              if (!acc[group]) {
                  acc[group] = [];
              }

              acc[group].push(opt);

              return acc;
          }, {})
        : null;

    const trimmedSearch = search.trim();
    const showCreateOption =
        creatable &&
        trimmedSearch.length > 0 &&
        !options.some(
            (o) => o.label.toLowerCase() === trimmedSearch.toLowerCase(),
        );

    function handleCreate() {
        onCreate?.(trimmedSearch);
        setSearch('');
        setOpen(false);
    }

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
                    <span className="inline-flex items-center gap-1.5 truncate">
                        {selectedColor && (
                            <span
                                className="inline-block h-2 w-2 shrink-0 rounded-full"
                                style={{ backgroundColor: selectedColor }}
                            />
                        )}
                        {selectedLabel}
                    </span>
                    <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-[--radix-popover-trigger-width] p-0"
                align="start"
                sideOffset={4}
            >
                <Command>
                    <CommandInput
                        placeholder={searchPlaceholder}
                        value={search}
                        onValueChange={setSearch}
                    />
                    <CommandList className="max-h-[200px] overflow-y-auto">
                        {!showCreateOption && (
                            <CommandEmpty>{emptyMessage}</CommandEmpty>
                        )}
                        {allOption && (
                            <CommandGroup>
                                <CommandItem
                                    value={allOption}
                                    onSelect={() => {
                                        onValueChange(null);
                                        setSearch('');
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
                                                    setSearch('');
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
                                                {option.color && (
                                                    <span
                                                        className="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                                                        style={{
                                                            backgroundColor:
                                                                option.color,
                                                        }}
                                                    />
                                                )}
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
                                            setSearch('');
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
                                        {option.color && (
                                            <span
                                                className="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                                                style={{
                                                    backgroundColor:
                                                        option.color,
                                                }}
                                            />
                                        )}
                                        {option.label}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        )}
                        {showCreateOption && (
                            <>
                                <CommandSeparator />
                                <CommandGroup>
                                    <CommandItem
                                        value={`create:${trimmedSearch}`}
                                        onSelect={handleCreate}
                                    >
                                        <PlusCircle className="mr-2 size-4" />
                                        Create &lsquo;{trimmedSearch}&rsquo;
                                    </CommandItem>
                                </CommandGroup>
                            </>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
