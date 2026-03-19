"use client"

import { format, parse } from "date-fns"
import { CalendarIcon } from "lucide-react"
import * as React from "react"
import type { DateRange } from "react-day-picker"

import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { cn } from "@/lib/utils"

interface DateRangePickerProps {
  from?: string
  to?: string
  onChange?: (range: { from: string; to: string }) => void
  placeholder?: string
  className?: string
}

function parseDate(value?: string): Date | undefined {
  if (!value) return undefined
  const normalized = value.slice(0, 10)
  const parsed = parse(normalized, "yyyy-MM-dd", new Date())
  if (isNaN(parsed.getTime())) return undefined
  return parsed
}

function DateRangePicker({
  from,
  to,
  onChange,
  placeholder = "Pick a date range",
  className,
}: DateRangePickerProps) {
  const [open, setOpen] = React.useState(false)

  const selected: DateRange | undefined = React.useMemo(() => {
    const fromDate = parseDate(from)
    const toDate = parseDate(to)
    if (!fromDate && !toDate) return undefined
    return { from: fromDate, to: toDate }
  }, [from, to])

  const handleSelect = React.useCallback(
    (range: DateRange | undefined) => {
      onChange?.({
        from: range?.from ? format(range.from, "yyyy-MM-dd") : "",
        to: range?.to ? format(range.to, "yyyy-MM-dd") : "",
      })
    },
    [onChange],
  )

  const displayText = React.useMemo(() => {
    const fromDate = parseDate(from)
    const toDate = parseDate(to)
    if (fromDate && toDate) {
      return `${format(fromDate, "MMM d, yyyy")} – ${format(toDate, "MMM d, yyyy")}`
    }
    if (fromDate) {
      return `${format(fromDate, "MMM d, yyyy")} – ...`
    }
    return placeholder
  }, [from, to, placeholder])

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          className={cn(
            "w-full justify-start text-left font-normal",
            !from && !to && "text-muted-foreground",
            className,
          )}
        >
          <CalendarIcon className="mr-2 size-4" />
          <span className="truncate">{displayText}</span>
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <Calendar
          mode="range"
          selected={selected}
          onSelect={handleSelect}
          defaultMonth={parseDate(from)}
          numberOfMonths={2}
          captionLayout="dropdown"
          startMonth={new Date(new Date().getFullYear() - 10, 0)}
          endMonth={new Date(new Date().getFullYear() + 10, 0)}
        />
      </PopoverContent>
    </Popover>
  )
}

export { DateRangePicker }
export type { DateRangePickerProps }
