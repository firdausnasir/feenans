export const appSidebarHeaderClassName =
    'flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 bg-sidebar px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4';

export const welcomeHeaderClassName =
    'border-b border-border bg-background/80 backdrop-blur-sm';

// Prevents SidebarInset from expanding the flex row beyond the viewport on medium screens.
// Without min-w-0, a flex item's min-width defaults to auto (its content width), which
// causes 256px sidebar + wide-content = page-level horizontal overflow at ~768px.
export const sidebarInsetClassName = 'min-w-0';
