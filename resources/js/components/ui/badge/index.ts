import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Badge } from "./Badge.vue"

// Indies sticker badge — `border-2 border-foreground` + uppercase
// `tracking-widest` + ink offset shadow, mirrors the eyebrow / channel
// badge pattern from the marketing site (NetworksGrid, PricingTables).
export const badgeVariants = cva(
  "inline-flex items-center justify-center w-fit shrink-0 whitespace-nowrap rounded-md border-2 border-foreground px-2 py-0.5 text-[10px] font-black uppercase tracking-widest gap-1 shadow-2xs [&>svg]:size-3 [&>svg]:pointer-events-none focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 transition-[color,box-shadow] overflow-hidden",
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground [a&]:hover:bg-primary/90",
        secondary:
          "bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90",
        destructive:
          "bg-destructive text-white [a&]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40",
        success:
          "bg-emerald-200 text-foreground [a&]:hover:bg-emerald-300",
        warning:
          "bg-amber-200 text-foreground [a&]:hover:bg-amber-300",
        outline:
          "bg-card text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)
export type BadgeVariants = VariantProps<typeof badgeVariants>
