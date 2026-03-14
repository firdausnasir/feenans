export type Currency = {
    code: string;
    name: string;
    symbol: string;
};

export const currencies: Currency[] = [
    { code: 'MYR', name: 'Malaysian Ringgit', symbol: 'RM' },
    { code: 'USD', name: 'US Dollar', symbol: '$' },
    { code: 'EUR', name: 'Euro', symbol: '\u20AC' },
    { code: 'GBP', name: 'British Pound', symbol: '\u00A3' },
    { code: 'SGD', name: 'Singapore Dollar', symbol: 'S$' },
    { code: 'JPY', name: 'Japanese Yen', symbol: '\u00A5' },
    { code: 'CNY', name: 'Chinese Yuan', symbol: '\u00A5' },
    { code: 'KRW', name: 'South Korean Won', symbol: '\u20A9' },
    { code: 'THB', name: 'Thai Baht', symbol: '\u0E3F' },
    { code: 'IDR', name: 'Indonesian Rupiah', symbol: 'Rp' },
    { code: 'PHP', name: 'Philippine Peso', symbol: '\u20B1' },
    { code: 'VND', name: 'Vietnamese Dong', symbol: '\u20AB' },
    { code: 'INR', name: 'Indian Rupee', symbol: '\u20B9' },
    { code: 'AUD', name: 'Australian Dollar', symbol: 'A$' },
    { code: 'NZD', name: 'New Zealand Dollar', symbol: 'NZ$' },
    { code: 'CAD', name: 'Canadian Dollar', symbol: 'C$' },
    { code: 'CHF', name: 'Swiss Franc', symbol: 'CHF' },
    { code: 'HKD', name: 'Hong Kong Dollar', symbol: 'HK$' },
    { code: 'TWD', name: 'Taiwan Dollar', symbol: 'NT$' },
    { code: 'AED', name: 'UAE Dirham', symbol: '\u062F.\u0625' },
    { code: 'SAR', name: 'Saudi Riyal', symbol: '\uFDFC' },
    { code: 'BRL', name: 'Brazilian Real', symbol: 'R$' },
    { code: 'MXN', name: 'Mexican Peso', symbol: 'MX$' },
    { code: 'ZAR', name: 'South African Rand', symbol: 'R' },
    { code: 'SEK', name: 'Swedish Krona', symbol: 'kr' },
    { code: 'NOK', name: 'Norwegian Krone', symbol: 'kr' },
    { code: 'DKK', name: 'Danish Krone', symbol: 'kr' },
    { code: 'PLN', name: 'Polish Zloty', symbol: 'z\u0142' },
    { code: 'TRY', name: 'Turkish Lira', symbol: '\u20BA' },
    { code: 'RUB', name: 'Russian Ruble', symbol: '\u20BD' },
    { code: 'BDT', name: 'Bangladeshi Taka', symbol: '\u09F3' },
    { code: 'PKR', name: 'Pakistani Rupee', symbol: '\u20A8' },
    { code: 'LKR', name: 'Sri Lankan Rupee', symbol: 'Rs' },
    { code: 'MMK', name: 'Myanmar Kyat', symbol: 'K' },
    { code: 'KHR', name: 'Cambodian Riel', symbol: '\u17DB' },
    { code: 'LAK', name: 'Lao Kip', symbol: '\u20AD' },
    { code: 'BND', name: 'Brunei Dollar', symbol: 'B$' },
    { code: 'NGN', name: 'Nigerian Naira', symbol: '\u20A6' },
    { code: 'EGP', name: 'Egyptian Pound', symbol: 'E\u00A3' },
    { code: 'KES', name: 'Kenyan Shilling', symbol: 'KSh' },
];

export const currencyCodeSet = new Set(currencies.map((c) => c.code));
