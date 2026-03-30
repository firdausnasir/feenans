export function buildAddTransactionSubmitOptions(): {
    preserveState: true;
    except: ['transactionModalData'];
} {
    return {
        preserveState: true,
        except: ['transactionModalData'],
    };
}
