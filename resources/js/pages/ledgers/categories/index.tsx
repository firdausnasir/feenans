import { Head, router } from '@inertiajs/react';
import { Tag, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import {
    destroy as destroyRoute,
    index as categoriesIndex,
    reorder as reorderRoute,
    store as storeRoute,
    update as updateRoute,
} from '@/routes/ledgers/categories';
import type { BreadcrumbItem, Category, Ledger } from '@/types';

// ── Types ────────────────────────────────────────────────────────────────────

type EditState = {
    categoryId: number;
    name: string;
    color: string;
    icon: string;
    parentId: number | null;
};

type AddSubState = {
    parentId: number;
    name: string;
};

type DeleteTarget = {
    category: Category;
};

type DeleteAction = 'uncategorize' | 'reassign';

// ── Helpers ──────────────────────────────────────────────────────────────────

function colorDot(color: string | null) {
    if (!color) {
        return (
            <span className="inline-block h-3 w-3 rounded-full border border-border bg-muted" />
        );
    }

    return (
        <span
            className="inline-block h-3 w-3 rounded-full border border-border"
            style={{ backgroundColor: color }}
        />
    );
}

function countTotalTransactions(category: Category): number {
    const own = category.transactions_count ?? 0;
    const childTotal = (category.children ?? []).reduce(
        (sum, child) => sum + (child.transactions_count ?? 0),
        0,
    );

    return own + childTotal;
}

function getReassignableCategoriesForDelete(
    allCategories: Category[],
    deleteTarget: Category,
): Category[] {
    const excludeIds = new Set<number>([deleteTarget.id]);

    for (const child of deleteTarget.children ?? []) {
        excludeIds.add(child.id);
    }

    const result: Category[] = [];

    for (const cat of allCategories) {
        if (excludeIds.has(cat.id)) {
            continue;
        }

        if (cat.transaction_type === deleteTarget.transaction_type) {
            result.push(cat);

            for (const child of cat.children ?? []) {
                if (!excludeIds.has(child.id)) {
                    result.push(child);
                }
            }
        }
    }

    return result;
}

function getAvailableParents(
    allCategories: Category[],
    currentCategory: Category,
): Category[] {
    return allCategories.filter(
        (c) =>
            c.id !== currentCategory.id &&
            c.parent_id === null &&
            c.transaction_type === currentCategory.transaction_type,
    );
}

// ── Sub-components ───────────────────────────────────────────────────────────

function InlineEditForm({
    edit,
    onChangeName,
    onChangeColor,
    onChangeIcon,
    onChangeParentId,
    onSave,
    onCancel,
    availableParents,
    isSubcategory,
}: {
    edit: EditState;
    onChangeName: (v: string) => void;
    onChangeColor: (v: string) => void;
    onChangeIcon: (v: string) => void;
    onChangeParentId: (v: number | null) => void;
    onSave: () => void;
    onCancel: () => void;
    availableParents: Category[];
    isSubcategory: boolean;
}) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <Input
                autoFocus
                value={edit.name}
                onChange={(e) => onChangeName(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        onSave();
                    } else if (e.key === 'Escape') {
                        onCancel();
                    }
                }}
                className="h-7 w-48 text-sm"
            />
            <div className="flex items-center gap-1">
                <Label className="sr-only" htmlFor={`color-${edit.categoryId}`}>
                    Color
                </Label>
                <input
                    id={`color-${edit.categoryId}`}
                    type="color"
                    value={edit.color || '#6b7280'}
                    onChange={(e) => onChangeColor(e.target.value)}
                    className="h-7 w-8 cursor-pointer rounded border border-border bg-transparent p-0.5"
                    title="Pick color"
                />
            </div>
            <Input
                value={edit.icon}
                onChange={(e) => onChangeIcon(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        onSave();
                    } else if (e.key === 'Escape') {
                        onCancel();
                    }
                }}
                placeholder="Icon (emoji)"
                className="h-7 w-24 text-sm"
            />
            {isSubcategory && availableParents.length > 0 && (
                <Select
                    value={String(edit.parentId ?? '')}
                    onValueChange={(v) =>
                        onChangeParentId(v ? Number(v) : null)
                    }
                >
                    <SelectTrigger className="h-7 w-40 text-xs" size="sm">
                        <SelectValue placeholder="Parent..." />
                    </SelectTrigger>
                    <SelectContent>
                        {availableParents.map((p) => (
                            <SelectItem key={p.id} value={String(p.id)}>
                                {p.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}
            <Button size="sm" className="h-7 px-2 text-xs" onClick={onSave}>
                Save
            </Button>
            <Button
                size="sm"
                variant="ghost"
                className="h-7 px-2 text-xs"
                onClick={onCancel}
            >
                Cancel
            </Button>
        </div>
    );
}

function AddCategoryForm({
    onSave,
    onCancel,
    transactionType,
}: {
    onSave: (name: string, color: string, icon: string) => void;
    onCancel: () => void;
    transactionType: 'expense' | 'income';
}) {
    const [name, setName] = useState('');
    const [color, setColor] = useState('#6b7280');
    const [icon, setIcon] = useState('');

    function handleSave() {
        if (name.trim()) {
            onSave(name.trim(), color, icon.trim());
        }
    }

    return (
        <div className="flex flex-wrap items-center gap-2 rounded-lg border border-dashed border-border p-3">
            <Input
                autoFocus
                value={name}
                onChange={(e) => setName(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        handleSave();
                    } else if (e.key === 'Escape') {
                        onCancel();
                    }
                }}
                placeholder={`New ${transactionType} category...`}
                className="h-7 w-52 text-sm"
            />
            <input
                type="color"
                value={color}
                onChange={(e) => setColor(e.target.value)}
                className="h-7 w-8 cursor-pointer rounded border border-border bg-transparent p-0.5"
                title="Pick color"
            />
            <Input
                value={icon}
                onChange={(e) => setIcon(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        handleSave();
                    } else if (e.key === 'Escape') {
                        onCancel();
                    }
                }}
                placeholder="Icon (emoji)"
                className="h-7 w-24 text-sm"
            />
            <Button size="sm" className="h-7 px-2 text-xs" onClick={handleSave}>
                Add
            </Button>
            <Button
                size="sm"
                variant="ghost"
                className="h-7 px-2 text-xs"
                onClick={onCancel}
            >
                Cancel
            </Button>
        </div>
    );
}

function AddSubcategoryForm({
    onSave,
    onCancel,
}: {
    onSave: (name: string) => void;
    onCancel: () => void;
}) {
    const [name, setName] = useState('');

    return (
        <div className="ml-8 flex items-center gap-2 py-1">
            <Input
                autoFocus
                value={name}
                onChange={(e) => setName(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' && name.trim()) {
                        onSave(name.trim());
                    } else if (e.key === 'Escape') {
                        onCancel();
                    }
                }}
                placeholder="Subcategory name..."
                className="h-7 w-44 text-sm"
            />
            <Button
                size="sm"
                className="h-7 px-2 text-xs"
                onClick={() => {
                    if (name.trim()) {
                        onSave(name.trim());
                    }
                }}
            >
                Add
            </Button>
            <Button
                size="sm"
                variant="ghost"
                className="h-7 px-2 text-xs"
                onClick={onCancel}
            >
                Cancel
            </Button>
        </div>
    );
}

// ── Delete confirmation dialog ───────────────────────────────────────────────

function DeleteCategoryDialog({
    deleteTarget,
    allCategories,
    ledgerId,
    onClose,
}: {
    deleteTarget: DeleteTarget | null;
    allCategories: Category[];
    ledgerId: number;
    onClose: () => void;
}) {
    const [isDeleting, setIsDeleting] = useState(false);
    const [deleteAction, setDeleteAction] =
        useState<DeleteAction>('uncategorize');
    const [reassignCategoryId, setReassignCategoryId] = useState<string>('');

    if (!deleteTarget) {
        return null;
    }

    const { category } = deleteTarget;
    const totalTransactions = countTotalTransactions(category);
    const childCount = (category.children ?? []).length;
    const reassignableCategories = getReassignableCategoriesForDelete(
        allCategories,
        category,
    );

    function handleDelete() {
        if (!deleteTarget) {
            return;
        }

        setIsDeleting(true);

        const reassignValue =
            deleteAction === 'reassign' && reassignCategoryId
                ? Number(reassignCategoryId)
                : null;

        router.delete(
            destroyRoute.url({
                ledger: ledgerId,
                category: deleteTarget.category.id,
            }),
            {
                data: {
                    reassign_category_id: reassignValue,
                },
                preserveScroll: true,
                onSuccess: () => {
                    setIsDeleting(false);
                    setDeleteAction('uncategorize');
                    setReassignCategoryId('');
                    onClose();
                    toast.success('Category deleted');
                },
                onError: (errors) => {
                    setIsDeleting(false);
                    const msg =
                        errors.category ??
                        errors.reassign_category_id ??
                        errors.message ??
                        Object.values(errors)[0] ??
                        'Cannot delete this category.';
                    toast.error(String(msg));
                },
            },
        );
    }

    function handleOpenChange(open: boolean) {
        if (!open) {
            setDeleteAction('uncategorize');
            setReassignCategoryId('');
            onClose();
        }
    }

    const canDelete =
        deleteAction === 'uncategorize' ||
        (deleteAction === 'reassign' && reassignCategoryId !== '');

    return (
        <Dialog open={deleteTarget !== null} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete category</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete{' '}
                        <strong>{category.name}</strong>?
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {childCount > 0 && (
                        <p className="text-sm text-amber-600 dark:text-amber-400">
                            This category has {childCount}{' '}
                            {childCount === 1 ? 'subcategory' : 'subcategories'}{' '}
                            that will also be deleted.
                        </p>
                    )}

                    {totalTransactions > 0 ? (
                        <>
                            <p className="text-sm text-muted-foreground">
                                This category has{' '}
                                <strong>{totalTransactions}</strong>{' '}
                                {totalTransactions === 1
                                    ? 'transaction'
                                    : 'transactions'}
                                . What should happen to them?
                            </p>

                            <div className="space-y-2">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setDeleteAction('uncategorize');
                                        setReassignCategoryId('');
                                    }}
                                    className={`w-full rounded-lg border p-3 text-left text-sm transition-colors ${
                                        deleteAction === 'uncategorize'
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border hover:bg-muted/50'
                                    }`}
                                >
                                    <span className="font-medium">
                                        Leave uncategorized
                                    </span>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Transactions will have no category
                                        assigned.
                                    </p>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setDeleteAction('reassign')}
                                    className={`w-full rounded-lg border p-3 text-left text-sm transition-colors ${
                                        deleteAction === 'reassign'
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border hover:bg-muted/50'
                                    }`}
                                >
                                    <span className="font-medium">
                                        Reassign to another category
                                    </span>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Move all transactions to a different
                                        category.
                                    </p>
                                </button>

                                {deleteAction === 'reassign' && (
                                    <div className="pt-1 pl-3">
                                        <Select
                                            value={reassignCategoryId}
                                            onValueChange={
                                                setReassignCategoryId
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Select a category..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {reassignableCategories.map(
                                                    (c) => (
                                                        <SelectItem
                                                            key={c.id}
                                                            value={String(c.id)}
                                                        >
                                                            {c.parent_id !==
                                                            null
                                                                ? `\u00A0\u00A0${c.name}`
                                                                : c.name}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                            </div>
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            This category has no transactions.
                        </p>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => handleOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={handleDelete}
                        disabled={isDeleting || !canDelete}
                    >
                        {isDeleting ? 'Deleting...' : 'Delete'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

// ── Category list for one tab ────────────────────────────────────────────────

function CategoryList({
    ledgerId,
    categories,
    transactionType,
    editState,
    setEditState,
    addSubState,
    setAddSubState,
    onDeleteRequest,
    onAddCategory,
    openAddFormTrigger,
}: {
    ledgerId: number;
    categories: Category[];
    transactionType: 'expense' | 'income';
    editState: EditState | null;
    setEditState: (s: EditState | null) => void;
    addSubState: AddSubState | null;
    setAddSubState: (s: AddSubState | null) => void;
    onDeleteRequest: (cat: Category) => void;
    onAddCategory: (
        name: string,
        color: string,
        icon: string,
        parentId?: number,
        transactionType?: 'expense' | 'income',
    ) => void;
    openAddFormTrigger?: number;
}) {
    const [showAddForm, setShowAddForm] = useState(false);
    const dragOverIdRef = useRef<number | null>(null);
    const [dragOverId, setDragOverId] = useState<number | null>(null);
    const addFormRef = useRef<HTMLDivElement>(null);
    const [prevTrigger, setPrevTrigger] = useState(openAddFormTrigger);

    // Open add form when parent triggers it
    if (
        openAddFormTrigger &&
        openAddFormTrigger > 0 &&
        openAddFormTrigger !== prevTrigger
    ) {
        setPrevTrigger(openAddFormTrigger);
        setShowAddForm(true);
    }

    // Scroll to add form after it becomes visible
    useEffect(() => {
        if (showAddForm) {
            const timer = setTimeout(() => {
                addFormRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            }, 100);

            return () => clearTimeout(timer);
        }
    }, [showAddForm]);

    // Only top-level categories for this tab
    const tabCats = categories.filter(
        (c) => c.transaction_type === transactionType && c.parent_id === null,
    );

    function startEdit(cat: Category) {
        setEditState({
            categoryId: cat.id,
            name: cat.name,
            color: cat.color ?? '',
            icon: cat.icon ?? '',
            parentId: cat.parent_id,
        });
    }

    function saveEdit() {
        if (!editState) {
            return;
        }

        router.put(
            updateRoute.url({
                ledger: ledgerId,
                category: editState.categoryId,
            }),
            {
                name: editState.name,
                color: editState.color || null,
                icon: editState.icon || null,
                parent_id: editState.parentId,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditState(null);
                    toast.success('Category updated');
                },
                onError: (errors) => {
                    const msg =
                        errors.name ??
                        errors.color ??
                        errors.icon ??
                        errors.parent_id ??
                        'Failed to update category.';
                    toast.error(msg);
                },
            },
        );
    }

    function saveSubcategory(
        parentId: number,
        name: string,
        transactionType: 'expense' | 'income',
    ) {
        onAddCategory(name, '', '', parentId, transactionType);
        setAddSubState(null);
    }

    // ── Drag & drop ──────────────────────────────────────────────────────────

    function handleDragStart(e: React.DragEvent, catId: number) {
        e.dataTransfer.setData('text/plain', String(catId));
        e.dataTransfer.effectAllowed = 'move';
    }

    function handleDragOver(e: React.DragEvent, catId: number) {
        e.preventDefault();

        if (dragOverIdRef.current !== catId) {
            dragOverIdRef.current = catId;
            setDragOverId(catId);
        }
    }

    function handleDragLeave() {
        dragOverIdRef.current = null;
        setDragOverId(null);
    }

    function handleDrop(e: React.DragEvent, targetId: number) {
        e.preventDefault();
        dragOverIdRef.current = null;
        setDragOverId(null);

        const draggedId = Number(e.dataTransfer.getData('text/plain'));

        if (draggedId === targetId) {
            return;
        }

        const reordered = [...tabCats];
        const fromIdx = reordered.findIndex((c) => c.id === draggedId);
        const toIdx = reordered.findIndex((c) => c.id === targetId);

        if (fromIdx === -1 || toIdx === -1) {
            return;
        }

        const [moved] = reordered.splice(fromIdx, 1);
        reordered.splice(toIdx, 0, moved);

        const items = reordered.map((c, i) => ({ id: c.id, position: i + 1 }));

        router.post(
            reorderRoute.url(ledgerId),
            { items },
            {
                preserveScroll: true,
                onError: () => {
                    toast.error('Failed to reorder categories.');
                },
            },
        );
    }

    return (
        <div className="space-y-1">
            {tabCats.length === 0 && !showAddForm && (
                <EmptyState
                    icon={<Tag className="size-6" />}
                    title={`No ${transactionType} categories yet`}
                    description="Organize your transactions with categories."
                />
            )}

            {tabCats.map((cat) => {
                const isEditing = editState?.categoryId === cat.id;
                const isDragOver = dragOverId === cat.id;
                const children = cat.children ?? [];
                const availableParents = getAvailableParents(categories, cat);

                return (
                    <div key={cat.id}>
                        {/* Parent row */}
                        <div
                            draggable
                            onDragStart={(e) => handleDragStart(e, cat.id)}
                            onDragOver={(e) => handleDragOver(e, cat.id)}
                            onDragLeave={handleDragLeave}
                            onDragEnd={() => {
                                dragOverIdRef.current = null;
                                setDragOverId(null);
                            }}
                            onDrop={(e) => handleDrop(e, cat.id)}
                            className={`group flex items-center gap-3 rounded-lg px-3 py-2 transition-colors ${
                                isDragOver
                                    ? 'border border-primary/40 bg-primary/5'
                                    : 'border border-transparent hover:bg-muted/50'
                            }`}
                        >
                            {/* Drag handle */}
                            <span
                                aria-hidden="true"
                                className="cursor-grab text-muted-foreground opacity-0 select-none group-hover:opacity-100"
                            >
                                :::
                            </span>

                            {/* Color dot */}
                            {colorDot(cat.color)}

                            {/* Icon */}
                            {cat.icon && (
                                <span className="text-base leading-none">
                                    {cat.icon}
                                </span>
                            )}

                            {/* Name / edit form */}
                            {isEditing && editState ? (
                                <InlineEditForm
                                    edit={editState}
                                    onChangeName={(v) =>
                                        setEditState({ ...editState, name: v })
                                    }
                                    onChangeColor={(v) =>
                                        setEditState({
                                            ...editState,
                                            color: v,
                                        })
                                    }
                                    onChangeIcon={(v) =>
                                        setEditState({ ...editState, icon: v })
                                    }
                                    onChangeParentId={(v) =>
                                        setEditState({
                                            ...editState,
                                            parentId: v,
                                        })
                                    }
                                    onSave={saveEdit}
                                    onCancel={() => setEditState(null)}
                                    availableParents={availableParents}
                                    isSubcategory={cat.parent_id !== null}
                                />
                            ) : (
                                <>
                                    <button
                                        type="button"
                                        onClick={() => startEdit(cat)}
                                        className="flex-1 text-left text-sm font-medium hover:underline"
                                    >
                                        {cat.name}
                                    </button>

                                    {(cat.transactions_count ?? 0) > 0 && (
                                        <span className="text-xs text-muted-foreground">
                                            {cat.transactions_count}
                                        </span>
                                    )}

                                    <div className="flex items-center gap-1 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-auto px-2 py-0.5 text-xs"
                                            onClick={() =>
                                                setAddSubState({
                                                    parentId: cat.id,
                                                    name: '',
                                                })
                                            }
                                        >
                                            + Sub
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-auto px-2 py-0.5 text-xs text-destructive hover:text-destructive"
                                            onClick={() => onDeleteRequest(cat)}
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </>
                            )}
                        </div>

                        {/* Children */}
                        {children.map((child) => {
                            const isChildEditing =
                                editState?.categoryId === child.id;
                            const childAvailableParents = getAvailableParents(
                                categories,
                                child,
                            );

                            return (
                                <div
                                    key={child.id}
                                    className="group ml-8 flex items-center gap-3 rounded-lg px-3 py-1.5 hover:bg-muted/50"
                                >
                                    {colorDot(child.color)}

                                    {child.icon && (
                                        <span className="text-base leading-none">
                                            {child.icon}
                                        </span>
                                    )}

                                    {isChildEditing && editState ? (
                                        <InlineEditForm
                                            edit={editState}
                                            onChangeName={(v) =>
                                                setEditState({
                                                    ...editState,
                                                    name: v,
                                                })
                                            }
                                            onChangeColor={(v) =>
                                                setEditState({
                                                    ...editState,
                                                    color: v,
                                                })
                                            }
                                            onChangeIcon={(v) =>
                                                setEditState({
                                                    ...editState,
                                                    icon: v,
                                                })
                                            }
                                            onChangeParentId={(v) =>
                                                setEditState({
                                                    ...editState,
                                                    parentId: v,
                                                })
                                            }
                                            onSave={saveEdit}
                                            onCancel={() => setEditState(null)}
                                            availableParents={
                                                childAvailableParents
                                            }
                                            isSubcategory={true}
                                        />
                                    ) : (
                                        <>
                                            <button
                                                type="button"
                                                onClick={() => startEdit(child)}
                                                className="flex-1 text-left text-sm text-muted-foreground hover:underline"
                                            >
                                                {child.name}
                                            </button>

                                            {(child.transactions_count ?? 0) >
                                                0 && (
                                                <span className="text-xs text-muted-foreground">
                                                    {child.transactions_count}
                                                </span>
                                            )}

                                            <div className="flex items-center gap-1 opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-auto px-2 py-0.5 text-xs text-destructive hover:text-destructive"
                                                    onClick={() =>
                                                        onDeleteRequest(child)
                                                    }
                                                >
                                                    Delete
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </div>
                            );
                        })}

                        {/* Add subcategory inline form */}
                        {addSubState?.parentId === cat.id && (
                            <AddSubcategoryForm
                                onSave={(name) =>
                                    saveSubcategory(
                                        cat.id,
                                        name,
                                        transactionType,
                                    )
                                }
                                onCancel={() => setAddSubState(null)}
                            />
                        )}
                    </div>
                );
            })}

            {/* Add parent category form */}
            <div ref={addFormRef}>
                {showAddForm ? (
                    <AddCategoryForm
                        transactionType={transactionType}
                        onSave={(name, color, icon) => {
                            onAddCategory(name, color, icon);
                            setShowAddForm(false);
                        }}
                        onCancel={() => setShowAddForm(false)}
                    />
                ) : (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="mt-2"
                        onClick={() => setShowAddForm(true)}
                    >
                        + Add Category
                    </Button>
                )}
            </div>
        </div>
    );
}

// ── Main page ────────────────────────────────────────────────────────────────

export default function CategoriesIndex({
    ledger,
    categories,
}: {
    ledger: Ledger;
    categories: Category[];
}) {
    const [editState, setEditState] = useState<EditState | null>(null);
    const [addSubState, setAddSubState] = useState<AddSubState | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<DeleteTarget | null>(null);
    const [activeTab, setActiveTab] = useState<'expense' | 'income'>('expense');
    const [addFormTrigger, setAddFormTrigger] = useState(0);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Categories', href: categoriesIndex.url(ledger.id) },
    ];

    function handleAddCategory(
        name: string,
        color: string,
        icon: string,
        parentId?: number,
        transactionType?: 'expense' | 'income',
    ) {
        router.post(
            storeRoute.url(ledger.id),
            {
                name,
                color: color || null,
                icon: icon || null,
                parent_id: parentId ?? null,
                transaction_type: transactionType,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Category created');
                },
                onError: (errors) => {
                    const msg =
                        errors.name ??
                        errors.transaction_type ??
                        'Failed to create category.';
                    toast.error(msg);
                },
            },
        );
    }

    function makeAddHandler(transactionType: 'expense' | 'income') {
        return (name: string, color: string, icon: string, parentId?: number) =>
            handleAddCategory(name, color, icon, parentId, transactionType);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ledger.name} categories`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Categories"
                        description="Manage categories for this ledger."
                    />
                    <Button
                        className="w-full sm:w-auto"
                        onClick={() => setAddFormTrigger((t) => t + 1)}
                    >
                        <Plus className="mr-1 size-4" />
                        Add Category
                    </Button>
                </div>

                <Tabs
                    defaultValue="expense"
                    onValueChange={(v) => {
                        setEditState(null);
                        setActiveTab(v as 'expense' | 'income');
                    }}
                >
                    <TabsList>
                        <TabsTrigger value="expense">Expense</TabsTrigger>
                        <TabsTrigger value="income">Income</TabsTrigger>
                    </TabsList>

                    <TabsContent value="expense" className="mt-4">
                        <CategoryList
                            ledgerId={ledger.id}
                            categories={categories}
                            transactionType="expense"
                            editState={editState}
                            setEditState={setEditState}
                            addSubState={addSubState}
                            setAddSubState={setAddSubState}
                            onDeleteRequest={(cat) =>
                                setDeleteTarget({ category: cat })
                            }
                            onAddCategory={makeAddHandler('expense')}
                            openAddFormTrigger={
                                activeTab === 'expense'
                                    ? addFormTrigger
                                    : undefined
                            }
                        />
                    </TabsContent>

                    <TabsContent value="income" className="mt-4">
                        <CategoryList
                            ledgerId={ledger.id}
                            categories={categories}
                            transactionType="income"
                            editState={editState}
                            setEditState={setEditState}
                            addSubState={addSubState}
                            setAddSubState={setAddSubState}
                            onDeleteRequest={(cat) =>
                                setDeleteTarget({ category: cat })
                            }
                            onAddCategory={makeAddHandler('income')}
                            openAddFormTrigger={
                                activeTab === 'income'
                                    ? addFormTrigger
                                    : undefined
                            }
                        />
                    </TabsContent>
                </Tabs>
            </div>

            {/* Delete confirmation dialog */}
            <DeleteCategoryDialog
                deleteTarget={deleteTarget}
                allCategories={categories}
                ledgerId={ledger.id}
                onClose={() => setDeleteTarget(null)}
            />
        </AppLayout>
    );
}
