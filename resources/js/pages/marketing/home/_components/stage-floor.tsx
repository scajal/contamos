/**
 * The stage's floor: a grid in perspective, anchored to the bottom of
 * whatever container it's placed in. It fades out only at the horizon;
 * the near edge is cut hard by the container's bottom border.
 *
 * The height is a whole number of grid rows and the horizontal lines are
 * offset by a pixel, so the last one lands on the container's final
 * pixel row and closes the stage like a border against the next section.
 */
export const StageFloor = () => (
    <div aria-hidden="true" className="pointer-events-none absolute inset-0">
        <div className="absolute bottom-0 left-[-60%] h-[880px] w-[220%] [perspective:900px]">
            <div className="size-full origin-bottom [transform:rotateX(66deg)] [background-image:linear-gradient(to_right,color-mix(in_oklch,var(--foreground)_18%,transparent)_1px,transparent_1px),linear-gradient(to_bottom,color-mix(in_oklch,var(--foreground)_18%,transparent)_1px,transparent_1px)] [mask-image:linear-gradient(to_top,black_0%,black_55%,transparent_88%)] [background-size:72px_100%,100%_110px] [background-position:0_0,0_-1px]" />
        </div>
    </div>
);
