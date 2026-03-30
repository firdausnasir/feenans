import { Deferred, Head, router, usePage } from '@inertiajs/react';
import { Hash, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    destroy as destroyTag,
    store as storeTag,
    update as updateTag,
} from '@/actions/App/Http/Controllers/Ledger/TagController';
import { TagPill } from '@/components/tag-pill';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { mapInertiaErrorsArray } from '@/lib/utils';
import { dashboard as ledgerDashboard } from '@/routes/ledgers';
import { index as tagsIndex } from '@/routes/ledgers/tags';
import type { BreadcrumbItem, Tag } from '@/types';

type TagWithCount = Tag & { transactions_count: number };

type FormState = {
    name: string;
    color: string;
};

const emptyForm = (): FormState => ({ name: '', color: '#6366f1' });

const PRESET_COLORS = [
    '#ef4444',
    '#f97316',
    '#eab308',
    '#22c55e',
    '#06b6d4',
    '#3b82f6',
    '#6366f1',
    '#a855f7',
    '#ec4899',
    '#64748b',
];

function TagsLoadingSkeleton() {
    return (
        <Card>
            <CardContent className="p-0">
                <div className="space-y-4 p-6">
                    {Array.from({ length: 5 }).map((_, i) => (
                        <div key={i} className="flex items-center gap-4">
                            <Skeleton className="h-4 w-24" />
                            <Skeleton className="h-4 w-4 rounded-full" />
                            <Skeleton className="h-4 w-12" />
                            <Skeleton className="ml-auto h-4 w-16" />
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

export default function TagsIndex() {
    const { currentLedger, tags: deferredTags } = usePage<{
        currentLedger: { id: number; name: string };
        tags?: TagWithCount[];
    }>().props;
    const ledger = currentLedger!;
    const tags = deferredTags ?? [];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: ledger.name, href: ledgerDashboard.url(ledger.id) },
        { title: 'Tags', href: tagsIndex.url(ledger.id) },
    ];

    const [showDialog, setShowDialog] = useState(false);
    const [editTag, setEditTag] = useState<TagWithCount | null>(null);
    const [form, setForm] = useState<FormState>(emptyForm());
    const [deleteTag, setDeleteTag] = useState<TagWithCount | null>(null);
    const [submitting, setSubmitting] = useState(false);

    function handleCreate() {
        setForm(emptyForm());
        setEditTag(null);
        setShowDialog(true);
    }

    function handleEdit(tag: TagWithCount) {
        setEditTag(tag);
        setForm({ name: tag.name, color: tag.color ?? '#6366f1' });
        setShowDialog(true);
    }

    async function handleSubmit() {
        setSubmitting(true);

        try {
            if (editTag) {
                router.patch(
                    updateTag.url({ ledger: ledger.id, tag: editTag.id }),
                    { name: form.name, color: form.color || null },
                    {
                        only: ['tags', 'flash'],
                        preserveState: true,
                        preserveScroll: true,
                        onSuccess: () => {
                            setShowDialog(false);
                            setEditTag(null);
                            toast.success('Tag updated');
                        },
                        onError: (errors) => {
                            const mapped = mapInertiaErrorsArray(errors);
                            toast.error(
                                mapped.name?.[0] ?? 'Failed to save tag.',
                            );
                        },
                        onFinish: () => setSubmitting(false),
                    },
                );
            } else {
                router.post(
                    storeTag.url(ledger.id),
                    { name: form.name, color: form.color || null },
                    {
                        only: ['tags', 'flash'],
                        preserveState: true,
                        preserveScroll: true,
                        onSuccess: () => {
                            setShowDialog(false);
                            toast.success('Tag created');
                        },
                        onError: (errors) => {
                            const mapped = mapInertiaErrorsArray(errors);
                            toast.error(
                                mapped.name?.[0] ?? 'Failed to save tag.',
                            );
                        },
                        onFinish: () => setSubmitting(false),
                    },
                );
            }

            return;
        } catch {
            toast.error('An unexpected error occurred');
            setSubmitting(false);
        }
    }

    async function handleDelete() {
        if (!deleteTag) {
            return;
        }

        try {
            router.delete(
                destroyTag.url({ ledger: ledger.id, tag: deleteTag.id }),
                {
                    only: ['tags', 'flash'],
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        setDeleteTag(null);
                        toast.success('Tag deleted');
                    },
                    onError: () => {
                        toast.error('Failed to delete tag');
                    },
                },
            );
        } catch {
            toast.error('An unexpected error occurred');
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Tags — ${ledger.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex justify-end">
                    <Button className="w-full sm:w-auto" onClick={handleCreate}>
                        <Plus className="mr-1 size-4" />
                        New Tag
                    </Button>
                </div>

                <Deferred data="tags" fallback={<TagsLoadingSkeleton />}>
                    {tags.length === 0 ? (
                        <EmptyState
                            icon={<Hash className="size-6" />}
                            title="No tags yet"
                            description="Create tags to organize and filter your transactions."
                            action={{
                                label: 'Create your first tag',
                                onClick: handleCreate,
                            }}
                        />
                    ) : (
                        <Card>
                            <CardContent className="p-0">
                                <div className="divide-y sm:hidden">
                                    {tags.map((tag) => (
                                        <div
                                            key={tag.id}
                                            className="flex items-center gap-3 px-4 py-3"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <TagPill tag={tag} />
                                                    {tag.color && (
                                                        <span
                                                            className="size-3 shrink-0 rounded-full"
                                                            style={{
                                                                backgroundColor:
                                                                    tag.color,
                                                            }}
                                                        />
                                                    )}
                                                </div>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {tag.transactions_count}{' '}
                                                    transaction
                                                    {tag.transactions_count !==
                                                    1
                                                        ? 's'
                                                        : ''}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-7 w-7"
                                                    onClick={() =>
                                                        handleEdit(tag)
                                                    }
                                                >
                                                    <Pencil className="h-3.5 w-3.5" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-7 w-7 text-destructive"
                                                    onClick={() =>
                                                        setDeleteTag(tag)
                                                    }
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <Table className="hidden sm:table">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Tag</TableHead>
                                            <TableHead>Color</TableHead>
                                            <TableHead className="text-right">
                                                Transactions
                                            </TableHead>
                                            <TableHead className="sr-only">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {tags.map((tag) => (
                                            <TableRow key={tag.id}>
                                                <TableCell>
                                                    <TagPill tag={tag} />
                                                </TableCell>
                                                <TableCell>
                                                    {tag.color ? (
                                                        <span
                                                            className="inline-block size-4 rounded-full border"
                                                            style={{
                                                                backgroundColor:
                                                                    tag.color,
                                                            }}
                                                        />
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">
                                                            None
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right text-muted-foreground">
                                                    {tag.transactions_count}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-auto px-2 py-0.5"
                                                            onClick={() =>
                                                                handleEdit(tag)
                                                            }
                                                        >
                                                            <Pencil className="size-3.5" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-auto px-2 py-0.5 text-destructive hover:text-destructive"
                                                            onClick={() =>
                                                                setDeleteTag(
                                                                    tag,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    )}
                </Deferred>
            </div>

            {/* Create / Edit Dialog */}
            <Dialog
                open={showDialog}
                onOpenChange={(open) => {
                    if (!open) {
                        setShowDialog(false);
                        setEditTag(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editTag ? 'Edit Tag' : 'New Tag'}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="flex flex-col gap-4 py-2">
                        <div className="grid gap-2">
                            <Label htmlFor="tag-name">Name</Label>
                            <Input
                                id="tag-name"
                                value={form.name}
                                onChange={(e) =>
                                    setForm((f) => ({
                                        ...f,
                                        name: e.target.value,
                                    }))
                                }
                                placeholder="e.g. Vacation, Tax Deductible"
                                maxLength={50}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label>Color</Label>
                            <div className="flex flex-wrap gap-2">
                                {PRESET_COLORS.map((color) => (
                                    <button
                                        key={color}
                                        type="button"
                                        className={`size-7 rounded-full border-2 transition-transform ${
                                            form.color === color
                                                ? 'scale-110 border-foreground'
                                                : 'border-transparent hover:scale-105'
                                        }`}
                                        style={{ backgroundColor: color }}
                                        onClick={() =>
                                            setForm((f) => ({ ...f, color }))
                                        }
                                    />
                                ))}
                            </div>
                            <Input
                                type="color"
                                value={form.color}
                                onChange={(e) =>
                                    setForm((f) => ({
                                        ...f,
                                        color: e.target.value,
                                    }))
                                }
                                className="h-8 w-20"
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setShowDialog(false);
                                setEditTag(null);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleSubmit}
                            disabled={!form.name.trim() || submitting}
                        >
                            {editTag ? 'Save changes' : 'Create tag'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete confirmation */}
            <Dialog
                open={deleteTag !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTag(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete tag</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete{' '}
                            <strong>{deleteTag?.name}</strong>? It will be
                            removed from all transactions.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleteTag(null)}
                        >
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
