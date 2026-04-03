const NOT_MAPPED = '__not_mapped__';

export type ParseResult = {
    headers: string[];
    preview_rows: string[][];
    total_rows: number;
    file_path: string;
    detected_bank?: string;
    suggested_mapping?: Record<string, string>;
};

export type Mapping = {
    date: string;
    amount: string;
    description: string;
    category: string;
    payee: string;
    type: string;
};

export function emptyMapping(): Mapping {
    return {
        date: NOT_MAPPED,
        amount: NOT_MAPPED,
        description: NOT_MAPPED,
        category: NOT_MAPPED,
        payee: NOT_MAPPED,
        type: NOT_MAPPED,
    };
}

export function deriveMapping(result: ParseResult): {
    mapping: Mapping;
    detectedBank: string | null;
} {
    if (result.detected_bank && result.suggested_mapping) {
        return {
            detectedBank: result.detected_bank,
            mapping: {
                date: result.suggested_mapping.date ?? NOT_MAPPED,
                amount: result.suggested_mapping.amount ?? NOT_MAPPED,
                description: result.suggested_mapping.description ?? NOT_MAPPED,
                category: result.suggested_mapping.category ?? NOT_MAPPED,
                payee: result.suggested_mapping.payee ?? NOT_MAPPED,
                type: result.suggested_mapping.type ?? NOT_MAPPED,
            },
        };
    }

    const autoMapping = emptyMapping();

    for (const header of result.headers) {
        const lower = header.toLowerCase();

        if (lower.includes('date') && autoMapping.date === NOT_MAPPED) {
            autoMapping.date = header;
        } else if (
            lower.includes('amount') &&
            autoMapping.amount === NOT_MAPPED
        ) {
            autoMapping.amount = header;
        } else if (
            (lower.includes('description') ||
                lower.includes('memo') ||
                lower.includes('narration')) &&
            autoMapping.description === NOT_MAPPED
        ) {
            autoMapping.description = header;
        } else if (
            (lower.includes('payee') ||
                lower.includes('merchant') ||
                lower.includes('vendor')) &&
            autoMapping.payee === NOT_MAPPED
        ) {
            autoMapping.payee = header;
        } else if (
            (lower.includes('type') || lower.includes('transaction type')) &&
            autoMapping.type === NOT_MAPPED
        ) {
            autoMapping.type = header;
        }
    }

    return {
        mapping: autoMapping,
        detectedBank: null,
    };
}

export function createInitialImportPageState(parseResult: ParseResult | null): {
    step: 1 | 2;
    parseResult: ParseResult | null;
    mapping: Mapping;
    detectedBank: string | null;
} {
    if (parseResult === null) {
        return {
            step: 1,
            parseResult: null,
            mapping: emptyMapping(),
            detectedBank: null,
        };
    }

    const mappingState = deriveMapping(parseResult);

    return {
        step: 2,
        parseResult,
        mapping: mappingState.mapping,
        detectedBank: mappingState.detectedBank,
    };
}

export function shouldBlockImportStepTwo({
    accountsError,
    accountsCount,
}: {
    accountsError: string | null;
    accountsCount: number;
    savedMappingsError: string | null;
    savedMappingsCount: number;
}): boolean {
    return accountsError !== null && accountsCount === 0;
}

export { NOT_MAPPED };
