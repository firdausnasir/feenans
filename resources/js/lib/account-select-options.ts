type SelectableAccount = {
    id: number;
    name: string;
    color: string | null;
    include_in_totals: boolean;
};

type AccountSelectOption = {
    value: string;
    label: string;
    color: string | null;
    group: 'Included in totals' | 'Savings';
};

export function buildAccountSelectOptions(
    accounts: SelectableAccount[],
    excludedAccountId?: string | null,
): AccountSelectOption[] {
    const includedOptions: AccountSelectOption[] = [];
    const excludedOptions: AccountSelectOption[] = [];

    for (const account of accounts) {
        if (excludedAccountId && String(account.id) === excludedAccountId) {
            continue;
        }

        const option: AccountSelectOption = {
            value: String(account.id),
            label: account.name,
            color: account.color,
            group: account.include_in_totals
                ? 'Included in totals'
                : 'Savings',
        };

        if (account.include_in_totals) {
            includedOptions.push(option);
        } else {
            excludedOptions.push(option);
        }
    }

    return [...includedOptions, ...excludedOptions];
}
