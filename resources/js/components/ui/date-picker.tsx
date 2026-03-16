"use client"

import { format, parse } from "date-fns"
import { CalendarIcon } from "lucide-react"
import * as React from "react"

import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { cn } from "@/lib/utils"

interface DatePickerProps {
  value?: string
  onChange?: (date: string) => void
  placeholder?: string
  id?: string
  name?: string
  disabled?: boolean
  className?: string
}

function DatePicker({
  value,
  onChange,
  placeholder = "Pick a date",
  id,
  name,
  disabled = false,
  className,
}: DatePickerProps) {
  const [open, setOpen] = React.useState(false)

  const selectedDate = React.useMemo(() => {
    if (!value) return undefined
    const normalized = value.slice(0, 10)
    const parsed = parse(normalized, "yyyy-MM-dd", new Date())
    if (isNaN(parsed.getTime())) return undefined
    return parsed
  }, [value])

  const handleSelect = React.useCallback(
    (date: Date | undefined) => {
      if (date) {
        onChange?.(format(date, "yyyy-MM-dd"))
      } else {
        onChange?.("")
      }
      setOpen(false)
    },
    [onChange],
  )

  return (
    <>
      {name && <input type="hidden" name={name} value={value ?? ""} />}
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            id={id}
            variant="outline"
            disabled={disabled}
            className={cn(
              "w-full justify-start text-left font-normal",
              !value && "text-muted-foreground",
              className,
            )}
          >
            <CalendarIcon className="mr-2 size-4" />
            {value && selectedDate
              ? format(selectedDate, "PPP")
              : placeholder}
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-auto p-0" align="start">
          <Calendar
            mode="single"
            selected={selectedDate}
            onSelect={handleSelect}
            defaultMonth={selectedDate}
            captionLayout="dropdown"
            startMonth={new Date(new Date().getFullYear() - 10, 0)}
            endMonth={new Date(new Date().getFullYear() + 10, 0)}
          />
        </PopoverContent>
      </Popover>
    </>
  )
}

export { DatePicker }
export type { DatePickerProps }

