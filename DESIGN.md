---
name: Contamos
description: Shared multi-currency ledger for groups that share money. Calm, typographic, colour only where it means something.
colors:
    ink: "oklch(0.2 0.012 60)"
    ink-soft: "oklch(0.24 0.012 60)"
    paper: "oklch(0.993 0.003 85)"
    card: "oklch(1 0 0)"
    paper-tint: "oklch(0.963 0.006 85)"
    graphite: "oklch(0.505 0.018 70)"
    hairline: "oklch(0.905 0.01 80)"
    ring: "oklch(0.69 0.018 75)"
    income-green: "oklch(0.52 0.13 150)"
    expense-red: "oklch(0.56 0.2 27)"
    brand-lime: "oklch(0.87 0.21 125)"
    night: "oklch(0.17 0.008 60)"
    night-card: "oklch(0.22 0.009 60)"
    night-muted: "oklch(0.28 0.01 60)"
    night-foreground: "oklch(0.975 0.003 85)"
    night-income: "oklch(0.72 0.15 150)"
    night-expense: "oklch(0.68 0.17 25)"
typography:
    display:
        fontFamily: "Switzer, Geist Variable, ui-sans-serif, system-ui, sans-serif"
        fontSize: "clamp(2rem, 4vw, 3.25rem)"
        fontWeight: 600
        lineHeight: 1.05
        letterSpacing: "-0.025em"
    headline:
        fontFamily: "Switzer, Geist Variable, ui-sans-serif, system-ui, sans-serif"
        fontSize: "1.75rem"
        fontWeight: 600
        lineHeight: 1.15
        letterSpacing: "-0.025em"
    title:
        fontFamily: "Switzer, Geist Variable, ui-sans-serif, system-ui, sans-serif"
        fontSize: "1.125rem"
        fontWeight: 600
        lineHeight: 1.375
        letterSpacing: "-0.025em"
    figure:
        fontFamily: "Switzer, Geist Variable, ui-sans-serif, system-ui, sans-serif"
        fontSize: "1.5rem"
        fontWeight: 600
        lineHeight: 1.4
        fontVariation: "tabular-nums"
    lead:
        fontFamily: "Geist Variable, ui-sans-serif, system-ui, sans-serif"
        fontSize: "15px"
        fontWeight: 400
        lineHeight: 1.625
    body:
        fontFamily: "Geist Variable, ui-sans-serif, system-ui, sans-serif"
        fontSize: "0.875rem"
        fontWeight: 400
        lineHeight: 1.5
    label:
        fontFamily: "Geist Variable, ui-sans-serif, system-ui, sans-serif"
        fontSize: "0.75rem"
        fontWeight: 500
        lineHeight: 1.33
rounded:
    sm: "0.45rem"
    md: "0.6rem"
    lg: "0.75rem"
    xl: "1.05rem"
    2xl: "1.35rem"
    3xl: "1.65rem"
    4xl: "1.95rem"
    pill: "9999px"
spacing:
    xs: "4px"
    sm: "8px"
    md: "16px"
    lg: "24px"
    xl: "32px"
    2xl: "48px"
components:
    button-primary:
        backgroundColor: "{colors.ink-soft}"
        textColor: "{colors.paper}"
        rounded: "{rounded.4xl}"
        height: "36px"
        padding: "0 12px"
        typography: "{typography.body}"
    button-outline:
        backgroundColor: "{colors.paper}"
        textColor: "{colors.ink}"
        rounded: "{rounded.4xl}"
        height: "36px"
        padding: "0 12px"
    button-ghost:
        backgroundColor: "transparent"
        textColor: "{colors.ink}"
        rounded: "{rounded.4xl}"
        height: "36px"
        padding: "0 12px"
    button-destructive:
        backgroundColor: "{colors.expense-red}"
        textColor: "{colors.expense-red}"
        rounded: "{rounded.4xl}"
        height: "36px"
        padding: "0 12px"
    input:
        backgroundColor: "{colors.hairline}"
        textColor: "{colors.ink}"
        rounded: "{rounded.3xl}"
        height: "36px"
        padding: "4px 12px"
    card:
        backgroundColor: "{colors.paper}"
        textColor: "{colors.ink}"
        rounded: "{rounded.xl}"
        padding: "16px"
    chip:
        backgroundColor: "{colors.paper}"
        textColor: "{colors.ink}"
        rounded: "{rounded.pill}"
        padding: "2px 8px"
        typography: "{typography.label}"
---

# Design System: Contamos

## Overview

**Creative North Star: "La mesa de la cocina"**

Contamos is where a couple, a few flatmates or a family sit down and go over the money they share. The interface should feel like that table: a clean sheet of paper, a pen, and someone you trust across from you. It is a tool, but it is a companion first. Numbers are the content; the chrome around them stays quiet. Warmth comes from air, from plain Spanish, and from the small lime dot that says "this is ours", not from decoration.

The system is a restrained near-monochrome one: ink on warm paper, hairline borders, pill buttons, Geist for everything that is read and Switzer for what is announced. Colour is semantic and rare: green means money came in, red means money went out, lime is the brand. That restraint stays. What carries the kitchen table is the warmth of the neutrals (hue ~75, chroma under 0.015, never blue-grey zinc), the air between sections, the softer corners, and plain Spanish written with tú.

**Key Characteristics:**

- Warm near-monochrome surfaces; colour only for meaning (income, expense, brand).
- Two voices: Geist for reading, Switzer for titles and for the big figures.
- Pill buttons and soft-rounded inputs; containers at `xl` so a pill never sits in a square box.
- Hairline borders for structure; almost no shadows.
- Tabular numbers everywhere a figure appears.
- Light and dark are both first class, and the device chooses when nobody has.
- Spanish first, tú, plain words ("movimiento", "gasto", "ingreso"), never accounting vocabulary.

## Colors

The palette is paper and ink with three semantic accents and one brand accent.

### Primary

- **Ink** (`oklch(0.2 0.012 60)`, `--foreground`): body text, icons, and primary buttons (`--primary` is the slightly lifted `oklch(0.24 0.012 60)`). In dark mode primary flips to near-white. It is a warm charcoal, never pure black.
- **Brand Lime** (`oklch(0.87 0.21 125)`, `--brand`, `#b2e016`): the dot in the BrandMark isotype. It is exposed as a token but is not a general UI accent; never use it for text, where it has too little contrast.

### Semantic

- **Income Green** (`oklch(0.52 0.13 150)`, `--success`): positive amounts, "income" toggle, non-default chip check marks. Dark: `oklch(0.72 0.15 150)`. Pulled back from neon so a column of amounts stays readable.
- **Expense Red** (`oklch(0.56 0.2 27)`, `--destructive`): negative amounts, destructive buttons (tinted 10% background, red text). Dark: `oklch(0.68 0.17 25)`.

### Neutral

- **Paper** (`oklch(0.993 0.003 85)`): the page background, a linen white rather than a pure one.
- **Card** (`oklch(1 0 0)`): pure white, so a card lifts off the page without a shadow.
- **Paper Tint** (`oklch(0.963 0.006 85)`): muted/secondary/accent surfaces, sidebar item hover, input fill (via `--input` at 50%).
- **Graphite** (`oklch(0.505 0.018 70)`): muted foreground (descriptions, labels, timestamps).
- **Hairline** (`oklch(0.905 0.01 80)`): borders, dividers, input fill.
- **Night** family (dark mode): background `0.17 0.008 60`, card and popover `0.22`, muted `0.28`, foreground `0.975`, borders white at 9%. Warm charcoal, not zinc.

### Named Rules

**The Sign Rule.** Green and red are reserved for the sign of money. An income is `text-success`, an expense is `text-destructive`, always with an explicit `+`/`-` prefix so colour is never the only signal. Nothing else on a screen is green or red except a destructive action.

**The Lime Dot Rule.** Brand lime lives in the isotype and in small marks such as an active indicator. It is never a text colour and never a large field.

**The Two Schemes Rule.** Every colour is a token that has a value in both schemes. A hardcoded hex, a `bg-white`, or a Tailwind palette colour such as `amber-500` is a bug: it only works in one of them.

## Typography

**Display Font:** Switzer (self-hosted variable, with Geist fallback)
**Body Font:** Geist Variable (system-ui fallback)
**Figures:** Switzer for the big totals, Geist for every figure in a row; both with `tabular-nums`

**Character:** Geist is neutral and legible at small sizes, so it carries the ledger. Switzer has more personality and is spent where the page announces itself: the page title, section headings, sheet titles, and the totals someone came to read. The pairing reads calm and modern; it should never read corporate.

### Hierarchy

- **Display** (600, `clamp(2rem, 4vw, 3.25rem)`, 1.05, tracking-tight): marketing only; not used inside the app.
- **Headline** (Switzer 600, 28px / `text-[1.75rem]`, 1.15, tracking-tight): the page title, one per page ("Movimientos", "Cuentas", the team name).
- **Title** (Switzer 600, 18px / `text-lg`, tracking-tight): sheet and dialog titles, section headings ("Últimos movimientos", "Detalles"). Section headings are `h2`; a page has a single `h1`.
- **Figure** (Switzer 600, 24px / `text-2xl`, tabular-nums): currency totals on the dashboard strip and the monthly cards.
- **Lead** (400, 15px, 1.625): the single line under a page title. It is prose, so it is a step larger and looser than body.
- **Body** (400, 14px / `text-sm`, 1.5): table cells, form labels, descriptions. Inputs use 16px on mobile to avoid iOS zoom.
- **Label** (500, 12px / `text-xs`): badges, chips, sidebar group labels, stat card captions.

### Named Rules

**The Tabular Rule.** Every currency amount and every time uses `tabular-nums` and right alignment so columns of figures line up.

**The Plain Words Rule.** Copy uses tú and everyday words. "Movimiento", "gasto", "ingreso", "cuenta", "etiqueta", "tipo de cambio". Never "transacción", "débito", "crédito", "asiento", "usted".

**The Gender-Free Template Rule.** A Spanish sentence that interpolates a noun cannot carry an article or an adjective, because the nouns differ in gender ("un movimiento", "una cuenta"). Write the template so it works with a plural noun instead: "Agrega :name para comenzar."

## Layout

- **Shell:** a 16rem sidebar (`--sidebar-width`) on the left; content in a `SidebarInset` with a 64px header (48px when the sidebar collapses to icons). Header holds the sidebar trigger, a breadcrumb, and the three ways to record money (Transferir ghost, Ingreso outline, Gasto primary). The sidebar marks the section the reader is on. On mobile the sidebar becomes a sheet, and the header keeps a `+` that records an expense directly with a chevron for the rest.
- **Content column:** `mx-auto w-full max-w-5xl min-w-0`, padded `px-12 py-12` on desktop and `px-5 py-6` on a phone. Pages open with a title block (Headline + one Lead line) and a right-aligned page action (period picker, create button).
- **Rhythm:** sections are separated by 32px; items inside a section by 8px; label-to-value by 4px. Stat cards sit in a bordered strip divided by hairlines rather than as floating cards.
- **Lists:** a movement is a table row from `md` up and a two-line `Item` below it, because the amount is the point of the row and a phone has no room for six columns. The two-line row reads `description … amount` on the first line and `account · time · tags` on the second. Movements group by day, headed "Hoy", "Ayer", or the full date.
- **Dialogs:** a dialog never exceeds `calc(100dvh - 2rem)`. A tall one keeps its header and footer in place and scrolls its middle section, so the title, the close button and the primary action stay reachable on a short screen.
- **Responsive:** single breakpoint of consequence at `sm`/`md`. Mobile stacks page actions full-width under the search field.

## Elevation & Depth

The system is flat. Depth is conveyed by hairline borders and tonal steps (paper, paper tint), not shadows. The only elevated surfaces are popovers, dropdowns and sheets, which use the shadcn defaults: a hairline border plus a soft shadow (`shadow-md`/`shadow-lg`) and a backdrop blur behind sheets. Focus is a 3px ring at 30% opacity of `--ring`.

### Named Rules

**The Flat Table Rule.** Cards and list containers never carry a shadow at rest. If two surfaces need separation, use a hairline or a tint step.

## Shapes

The base radius is `0.75rem` and every step derives from it. Two families coexist on purpose:

- **Controls are pills.** Buttons use `rounded-4xl` (31px, a pill at 36px height); inputs and search fields use `rounded-3xl` (26px); badges and chips are full pills.
- **Containers are soft rectangles.** Cards, list containers and stat strips use `rounded-xl` (17px), which is the outer radius a pill control needs so the two read as concentric. Dialogs use `rounded-4xl`.

Icons are Lucide at 16px (`size-4`), 1.5px stroke, `currentColor`. Avatars are circles; the team avatar is a 32px rounded square with initials.

## Components

### Buttons

- **Shape:** pill (`rounded-4xl`), 36px tall (`h-9`), 12px horizontal padding, 14px medium text, icon 16px with 6px gap.
- **Primary:** Ink Soft background, Paper text; hover to 80% opacity. One per view: the "Gasto" header action, or a page's create button.
- **Outline:** Paper background with Hairline border; hover Paper Tint. Used for the sibling action ("Ingreso", period pickers, "Editar").
- **Ghost:** transparent; hover Paper Tint. Icon-only toolbar buttons (`size-9`).
- **Destructive:** Expense Red at 10% background with red text; hover 20%. Used inside the "Peligro" section.
- **Press:** `translate-y-px` on active. Focus: 3px ring.

### Chips (movement chips and tags)

- **Movement chips** (date, notes, tags, rate): outline pills, 36px, icon + label, that open popovers. A green check icon on the right marks a non-default value.
- **Tag badges:** outline pills, 12px text, with a `+N` overflow badge.

### Cards / Containers

- **Corner Style:** `rounded-lg` (10px).
- **Background:** Paper; Paper Tint for muted states.
- **Border:** 1px Hairline. Stat strips divide cells with hairlines instead of gaps.
- **Internal Padding:** 16px; list rows 16px horizontal, 64px tall.

### Inputs / Fields

- **Style:** filled (`bg-input/50`, roughly Paper Tint), transparent border, `rounded-3xl`, 36px tall, 16px text on mobile and 14px on desktop.
- **Focus:** border becomes `--ring`, 3px ring at 30%.
- **Error:** border and ring go Expense Red (`aria-invalid`).
- **Amount input:** large right-aligned tabular figure with a currency select on the left, inside a taller pill.

### Navigation

- **Sidebar:** `--sidebar` background (near Paper), 14px labels with 16px Lucide icons, grouped under 12px Graphite labels ("Finance", "Equipo", "Ajustes"). Active item gets Paper Tint background and Ink text. Header is the team switcher (initials avatar, "Equipo" caption, team name, chevrons). Footer is the user menu (avatar, name, email).
- **Header:** breadcrumb of the current page; global Ingreso/Gasto actions on the right.
- **Mobile:** sidebar as an 18rem sheet from the left; header keeps trigger, breadcrumb and a single `+` action.

### Sheets (signature)

Every create/edit flow is an Inertia modal rendered as a right-side Sheet: title (Switzer 18px) and one-line description in the header, fields in a `px-6` body, a full-width primary button in the footer. The create-transaction sheet is amount-first: an Ingreso/Gasto toggle, account select, the big amount pill, then a row of movement chips.

### Stat strip

The dashboard's balance strip: one bordered container, one cell per currency, caption in Graphite 12px and the figure in 20px tabular. Cells divide with hairlines.

## Do's and Don'ts

### Do:

- **Do** keep amounts tabular, right-aligned, and signed (`+ US$ 2.261,62`, `- $ 7.486,75`).
- **Do** show every amount without scrolling, at every width.
- **Do** read a decimal comma as a decimal point on the server: a Spanish keyboard writes "350,50".
- **Do** use one primary button per view; everything else is outline or ghost.
- **Do** put create/edit flows in Sheets with `SheetHeader`/`SheetFooter` and `px-6` bodies.
- **Do** write Spanish copy with tú and everyday words; translate every string through `t()`.
- **Do** format dates through date-fns with the `es` locale when the UI is Spanish.
- **Do** keep hairline borders as the structural device; add a tint step before you add a shadow.

### Don't:

- **Don't** use brand lime anywhere but the isotype.
- **Don't** use green or red for anything but the sign of money and destructive actions.
- **Don't** mix radius families: no pill cards, no square buttons.
- **Don't** wrap a Table in `overflow-hidden`; let it scroll.
- **Don't** hide the amount column on mobile; below `md` there is no table, there is a two-line row.
- **Don't** format a date or a percentage with an English pattern; both go through Intl with the current locale.
- **Don't** animate a chart series in. The reader came for the figure, and a growth animation leaves the chart blank whenever frames are throttled.
- **Don't** add decorative illustration, gradients or glassmorphism to the app shell; warmth comes from space and words.
