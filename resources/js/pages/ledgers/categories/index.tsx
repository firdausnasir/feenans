import { Head, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

// ─── Types ────────────────────────────────────────────────────────────────────

type EditState = {
    categoryId: number;
    name: string;
    color: string;
    icon: string;
};

type AddSubState = {
    parentId: number;
    name: string;
};

type DeleteTarget = {
    category: Category;
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

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

// ─── Sub-components ───────────────────────────────────────────────────────────

function InlineEditForm({
    edit,
    onChangeName,
    onChangeColor,
    onChangeIcon,
    onSave,
    onCancel,
}: {
    edit: EditState;
    onChangeName: (v: string) => void;
    onChangeColor: (v: string) => void;
    onChangeIcon: (v: string) => void;
    onSave: () => void;
    onCancel: () => void;
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
                placeholder={`New ${transactionType} category…`}
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
                placeholder="Subcategory name…"
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

// ─── Category list for one tab ────────────────────────────────────────────────

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
}) {
    const [showAddForm, setShowAddForm] = useState(false);
    const dragOverIdRef = useRef<number | null>(null);
    const [dragOverId, setDragOverId] = useState<number | null>(null);

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
                <p className="py-4 text-sm text-muted-foreground">
                    No {transactionType} categories yet.
                </p>
            )}

            {tabCats.map((cat) => {
                const isEditing = editState?.categoryId === cat.id;
                const isDragOver = dragOverId === cat.id;
                const children = cat.children ?? [];

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
                                ⋮⋮
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
                                    onSave={saveEdit}
                                    onCancel={() => setEditState(null)}
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

                                    <div className="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
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
                                            onSave={saveEdit}
                                            onCancel={() => setEditState(null)}
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

                                            <div className="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
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
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────

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
    const [isDeleting, setIsDeleting] = useState(false);

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

    function handleDelete() {
        if (!deleteTarget) {
            return;
        }

        const { category } = deleteTarget;

        setIsDeleting(true);

        router.delete(
            destroyRoute.url({
                ledger: ledger.id,
                category: category.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsDeleting(false);
                    setDeleteTarget(null);
                    toast.success('Category deleted');
                },
                onError: (errors) => {
                    setIsDeleting(false);
                    setDeleteTarget(null);
                    const msg =
                        errors.category ??
                        errors.message ??
                        Object.values(errors)[0] ??
                        'Cannot delete this category.';
                    toast.error(String(msg));
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

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Categories"
                    description="Manage categories for this ledger."
                />

                <Tabs
                    defaultValue="expense"
                    onValueChange={() => setEditState(null)}
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
                        />
                    </TabsContent>
                </Tabs>
            </div>

            {/* Delete confirmation dialog */}
            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete category</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete{' '}
                            <strong>{deleteTarget?.category.name}</strong>? This
                            cannot be undone. If transactions or subcategories
                            reference this category, the deletion will be
                            rejected.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteTarget(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={isDeleting}
                        >
                            {isDeleting ? 'Deleting…' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
