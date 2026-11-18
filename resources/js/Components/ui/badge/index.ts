import { cva, type VariantProps } from 'class-variance-authority';

export { default as Badge } from './Badge.vue';

export const badgeVariants = cva('inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium', {
    variants: {
        variant: {
            default: 'bg-primary/10 text-primary border-transparent',
            success: 'bg-success/15 text-success border-transparent',
            warning: 'bg-warning/20 text-foreground border-transparent',
            destructive: 'bg-destructive/15 text-destructive border-transparent',
            outline: 'text-muted-foreground',
        },
    },
    defaultVariants: { variant: 'default' },
});
export type BadgeVariants = VariantProps<typeof badgeVariants>;
