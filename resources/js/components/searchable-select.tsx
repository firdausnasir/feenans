import { Check, ChevronsUpDown, PlusCircle, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
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

type BaseProps = {
    options: Option[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    className?: string;
    /** When true, shows a "Create '[search]'" option when the search text has no exact match */
    creatable?: boolean;
    /** Called with the typed name when the user picks the "Create" option */
    onCreate?: (name: string) => void;
    /** Label shown for the create entry, receives the search text. Defaults to "Create '[name]'" */
    createLabel?: string;
};

type SingleProps = BaseProps & {
    multiple?: false;
    value: string | null;
    onValueChange: (value: string | null) => void;
    /** If provided, adds this as the first option (e.g. "All accounts") that maps to null value */
    allOption?: string;
};

type MultiProps = BaseProps & {
    multiple: true;
    value: string[];
    onValueChange: (value: string[]) => void;
    allOption?: never;
};

type SearchableSelectProps = SingleProps | MultiProps;

export function SearchableSelect(props: SearchableSelectProps) {
    const {
        options,
        placeholder = 'Select...',
        searchPlaceholder = 'Search...',
        emptyMessage = 'No results found.',
        className,
        creatable = false,
        onCreate,
        createLabel,
    } = props;

    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const isMulti = props.multiple === true;

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

    // Multi-select helpers
    function isSelected(val: string): boolean {
        if (isMulti) {
            return (props as MultiProps).value.includes(val);
        }

        return (props as SingleProps).value === val;
    }

    function handleSelect(val: string) {
        if (isMulti) {
            const multiProps = props as MultiProps;
            const current = multiProps.value;
            const next = current.includes(val)
                ? current.filter((v) => v !== val)
                : [...current, val];
            multiProps.onValueChange(next);
            // Keep popover open for multi-select
        } else {
            const singleProps = props as SingleProps;
            singleProps.onValueChange(val === singleProps.value ? null : val);
            setSearch('');
            setOpen(false);
        }
    }

    function handleClearAll() {
        if (isMulti) {
            (props as MultiProps).onValueChange([]);
        }
    }

    // Render trigger content
    function renderTriggerContent() {
        if (isMulti) {
            const multiProps = props as MultiProps;
            const selected = multiProps.value;

            if (selected.length === 0) {
                return (
                    <span className="text-muted-foreground">{placeholder}</span>
                );
            }

            if (selected.length <= 1) {
                return (
                    <span className="flex items-center gap-1 truncate">
                        {selected.map((val) => {
                            const opt = options.find((o) => o.value === val);

                            return (
                                <Badge
                                    key={val}
                                    variant="secondary"
                                    className="px-1.5 py-0 text-xs font-normal"
                                >
                                    {opt?.color && (
                                        <span
                                            className="mr-1 inline-block h-2 w-2 shrink-0 rounded-full"
                                            style={{
                                                backgroundColor: opt.color,
                                            }}
                                        />
                                    )}
                                    {opt?.label ?? val}
                                </Badge>
                            );
                        })}
                    </span>
                );
            }

            return (
                <span className="flex items-center gap-1 truncate">
                    <Badge
                        variant="secondary"
                        className="px-1.5 py-0 text-xs font-normal"
                    >
                        {selected.length} selected
                    </Badge>
                </span>
            );
        }

        // Single select
        const singleProps = props as SingleProps;
        const selectedOption = singleProps.value
            ? options.find((o) => o.value === singleProps.value)
            : null;
        const selectedLabel = singleProps.value
            ? (selectedOption?.label ?? (createLabel || singleProps.value))
            : (singleProps.allOption ?? placeholder);
        const selectedColor = selectedOption?.color ?? null;

        return (
            <span className="inline-flex items-center gap-1.5 truncate">
                {selectedColor && (
                    <span
                        className="inline-block h-2 w-2 shrink-0 rounded-full"
                        style={{ backgroundColor: selectedColor }}
                    />
                )}
                {selectedLabel}
            </span>
        );
    }

    function renderOptionItem(option: Option) {
        return (
            <CommandItem
                key={option.value}
                value={option.label}
                onSelect={() => handleSelect(option.value)}
            >
                <Check
                    className={cn(
                        'mr-2 size-4',
                        isSelected(option.value) ? 'opacity-100' : 'opacity-0',
                    )}
                />
                {option.color && (
                    <span
                        className="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                        style={{ backgroundColor: option.color }}
                    />
                )}
                {option.label}
            </CommandItem>
        );
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
                        !isMulti &&
                            !(props as SingleProps).value &&
                            'text-muted-foreground',
                        isMulti &&
                            (props as MultiProps).value.length === 0 &&
                            'text-muted-foreground',
                        className,
                    )}
                >
                    {renderTriggerContent()}
                    <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-[--radix-popover-trigger-width] p-0"
                align="start"
                sideOffset={4}
                onWheel={(e) => e.stopPropagation()}
            >
                <Command>
                    <CommandInput
                        placeholder={searchPlaceholder}
                        value={search}
                        onValueChange={setSearch}
                    />
                    <CommandList>
                        {!showCreateOption && (
                            <CommandEmpty>{emptyMessage}</CommandEmpty>
                        )}
                        {!isMulti && (props as SingleProps).allOption && (
                            <CommandGroup>
                                <CommandItem
                                    value={(props as SingleProps).allOption!}
                                    onSelect={() => {
                                        (props as SingleProps).onValueChange(
                                            null,
                                        );
                                        setSearch('');
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 size-4',
                                            (props as SingleProps).value ===
                                                null
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    {(props as SingleProps).allOption}
                                </CommandItem>
                            </CommandGroup>
                        )}
                        {isMulti && (props as MultiProps).value.length > 0 && (
                            <CommandGroup>
                                <CommandItem
                                    value="__clear_all__"
                                    onSelect={handleClearAll}
                                >
                                    <X className="mr-2 size-4" />
                                    Clear all
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
                                        {groupOptions.map(renderOptionItem)}
                                    </CommandGroup>
                                ),
                            )
                        ) : (
                            <CommandGroup>
                                {options.map(renderOptionItem)}
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
