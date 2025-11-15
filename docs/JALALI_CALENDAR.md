# Jalali Calendar Support for Deck

This document describes the Jalali calendar (Persian/Shamsi calendar) support that has been added to the Deck application.

## Overview

The Deck application now supports the Jalali calendar system, which is the official calendar of Iran and Afghanistan. This feature provides:

- Automatic detection of Persian/Farsi locale
- Conversion between Gregorian and Jalali dates
- Persian date formatting and display
- Localized day and month names
- Persian calendar-aware due date calculations

## Features

### 1. Automatic Locale Detection

The system automatically detects when the user's locale is set to Persian/Farsi (`fa`, `fa-ir`, or `persian`) and switches to Jalali calendar mode.

### 2. Date Conversion

- **Gregorian to Jalali**: Automatically converts standard dates to Jalali format
- **Jalali to Gregorian**: Converts Jalali dates back to Gregorian for backend storage

### 3. Localized Display

- Persian day names (یکشنبه, دوشنبه, etc.)
- Persian month names (فروردین, اردیبهشت, etc.)
- Persian relative time expressions (امروز, فردا, etc.)

### 4. Enhanced Components

- **EnhancedDueDateSelector**: Due date picker with Jalali calendar support
- **EnhancedDueDate**: Due date badge with Persian calendar formatting
- **Enhanced Readable Date Mixin**: Provides calendar-aware date formatting

## Installation

The Jalali calendar support is automatically included when you install the Deck application. The required dependencies are:

```json
{
  "moment-jalaali": "^0.10.0"
}
```

## Usage

### For Users

1. **Set Persian Locale**: Change your Nextcloud locale to Persian/Farsi in your user settings
2. **Automatic Switch**: The calendar will automatically switch to Jalali mode
3. **Date Display**: All dates will be shown in Persian calendar format
4. **Due Dates**: Set and view due dates using the Persian calendar

### For Developers

#### Using the Jalali Calendar Helper

```javascript
import { 
  isPersianLocale, 
  toJalali, 
  formatJalaliDate,
  getPersianMonthNames 
} from './helpers/jalaliCalendar.js'

// Check if current locale uses Persian calendar
if (isPersianLocale()) {
  // Convert Gregorian date to Jalali
  const jalaliDate = toJalali(new Date())
  
  // Format Jalali date
  const formatted = formatJalaliDate(new Date(), 'jYYYY jMMMM jD')
  
  // Get Persian month names
  const months = getPersianMonthNames()
}
```

#### Using the Enhanced Date Mixin

```javascript
import enhancedReadableDate from './mixins/enhancedReadableDate.js'

export default {
  mixins: [enhancedReadableDate],
  computed: {
    formattedDate() {
      // Automatically uses Jalali calendar for Persian locale
      return this.formatEnhancedDate(this.timestamp)
    }
  }
}
```

#### Calendar-Aware Components

```vue
<template>
  <EnhancedDueDateSelector 
    :card="card" 
    :can-edit="true" 
    @change="handleDateChange" 
  />
</template>

<script>
import EnhancedDueDateSelector from './EnhancedDueDateSelector.vue'

export default {
  components: {
    EnhancedDueDateSelector
  }
}
</script>
```

## Calendar System Details

### Jalali Calendar Structure

- **Year**: Starts from 1 AH (622 CE)
- **Months**: 12 months, first 6 months have 31 days, next 5 have 30 days, last month has 29/30 days
- **Week**: Starts on Saturday (شنبه)
- **Leap Years**: Calculated using a 33-year cycle

### Date Format Examples

- **Short**: `1402/12/25` (Year/Month/Day)
- **Medium**: `25 اسفند 1402`
- **Long**: `شنبه 25 اسفند 1402`
- **Full**: `شنبه 25 اسفند 1402 ساعت 14:30`

### Relative Time Expressions

- **Today**: امروز
- **Tomorrow**: فردا
- **Yesterday**: دیروز
- **Next Week**: هفته آینده
- **Last Week**: هفته گذشته

## Configuration

### Locale Settings

The system automatically detects the following locale codes:
- `fa` - Persian
- `fa-ir` - Persian (Iran)
- `persian` - Persian (alternative)

### Moment.js Integration

The system extends Moment.js with Jalali calendar support using the `moment-jalaali` plugin, providing:
- `jMoment()` method for Jalali dates
- Jalali date formatting tokens (`jYYYY`, `jM`, `jD`)
- Persian locale configuration

## Backend Compatibility

- **Storage**: All dates are stored in standard ISO format in the database
- **API**: The backend continues to work with standard Gregorian dates
- **Conversion**: Frontend automatically converts between calendar systems

## Testing

### Manual Testing

1. Change your Nextcloud locale to Persian
2. Create a card with a due date
3. Verify the date is displayed in Jalali format
4. Check that relative time expressions use Persian text

### Automated Testing

The enhanced components include proper test coverage for both calendar systems.

## Troubleshooting

### Common Issues

1. **Dates not converting**: Ensure the locale is properly set to Persian
2. **Formatting errors**: Check that moment-jalaali is properly imported
3. **Locale detection issues**: Verify the locale code matches supported values

### Debug Information

Enable console logging to see calendar initialization:
```javascript
// Check current calendar type
console.log('Current calendar:', getCurrentCalendarType())

// Check if Persian locale is active
console.log('Is Persian locale:', isPersianLocale())
```

## Future Enhancements

Potential improvements for future versions:
- Customizable date formats
- Additional Persian calendar features (holidays, etc.)
- Support for other non-Gregorian calendars
- Enhanced date picker with visual calendar grid

## Contributing

When contributing to Jalali calendar support:
1. Maintain backward compatibility with existing date functionality
2. Follow the existing code style and patterns
3. Include proper tests for both calendar systems
4. Update documentation for any new features

## License

This feature is part of the Deck application and follows the same licensing terms (AGPL-3.0-or-later).
