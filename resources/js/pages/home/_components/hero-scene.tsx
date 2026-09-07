import { m, useMotionValue, useReducedMotion, useSpring, useTransform } from "motion/react";
import type { MotionValue } from "motion/react";
import { useEffect } from "react";

import { cn } from "@/lib/utils";

interface SceneObject {
    src: string;
    size: number;
    /** Position within the scene, as Tailwind inset utilities. */
    position: string;
    blur: number;
    opacity: number;
    rotate: string;
    /** How far the piece travels. Nearer pieces travel further, far ones barely stir. */
    depth: number;
    /** Its own spring: light far pieces snap, heavy near ones lag behind. */
    spring: { stiffness: number; damping: number; mass: number };
    /** Nearer pieces sit in front of the copy. */
    front?: boolean;
}

const OBJECTS: Array<SceneObject> = [
    // Far: small, hazy — the back of the room.
    {
        src: "pound",
        size: 56,
        position: "top-24 left-[13%] hidden lg:block",
        blur: 4,
        opacity: 0.45,
        rotate: "-rotate-12",
        depth: 0.15,
        spring: { stiffness: 150, damping: 22, mass: 0.4 },
    },
    {
        src: "yuan",
        size: 64,
        position: "top-16 right-[17%] hidden lg:block",
        blur: 4,
        opacity: 0.4,
        rotate: "rotate-6",
        depth: 0.22,
        spring: { stiffness: 135, damping: 21, mass: 0.45 },
    },
    {
        src: "bill",
        size: 84,
        position: "top-44 right-[8%] hidden lg:block",
        blur: 2,
        opacity: 0.75,
        rotate: "rotate-12",
        depth: 0.4,
        spring: { stiffness: 110, damping: 20, mass: 0.6 },
    },
    // Mid: readable, barely hazy.
    {
        src: "euro",
        size: 112,
        position: "top-56 left-[5%] hidden md:block",
        blur: 1,
        opacity: 0.95,
        rotate: "-rotate-6",
        depth: 0.7,
        spring: { stiffness: 85, damping: 18, mass: 0.85 },
    },
    {
        src: "credit-card",
        size: 140,
        position: "top-72 right-[4%] hidden md:block",
        blur: 1,
        opacity: 0.95,
        rotate: "rotate-6",
        depth: 0.85,
        spring: { stiffness: 72, damping: 17, mass: 1 },
    },
    // Near: large, sharp, in front of the content — closest to the camera.
    {
        src: "dollar",
        size: 148,
        position: "bottom-16 left-[6%]",
        blur: 0,
        opacity: 1,
        rotate: "-rotate-12",
        depth: 1.25,
        spring: { stiffness: 52, damping: 15, mass: 1.3 },
        front: true,
    },
    {
        src: "wallet",
        size: 176,
        position: "-bottom-4 right-[5%]",
        blur: 0,
        opacity: 1,
        rotate: "rotate-6",
        depth: 1.55,
        spring: { stiffness: 42, damping: 14, mass: 1.5 },
        front: true,
    },
    {
        src: "moneybag",
        size: 132,
        position: "-bottom-8 left-[26%] hidden xl:block",
        blur: 0,
        opacity: 1,
        rotate: "rotate-3",
        depth: 1.85,
        spring: { stiffness: 34, damping: 13, mass: 1.7 },
        front: true,
    },
];

/** How far the nearest piece drifts, in pixels, from one edge of the viewport to the other. */
const DRIFT = 110;

/**
 * One piece of the diorama. Each tracks the cursor at a speed set by
 * its depth — the near pieces swing a long way, the far ones barely
 * stir — and that difference in speed is what reads as depth.
 */
const SceneObjectLayer = ({
    object,
    pointerX,
    pointerY,
}: {
    object: SceneObject;
    pointerX: MotionValue<number>;
    pointerY: MotionValue<number>;
}) => {
    // Each piece smooths the same cursor input with its own spring, so
    // they arrive at different moments instead of gliding as one plane.
    const smoothX = useSpring(pointerX, object.spring);
    const smoothY = useSpring(pointerY, object.spring);
    const x = useTransform(smoothX, (value) => value * object.depth * DRIFT);
    const y = useTransform(smoothY, (value) => value * object.depth * (DRIFT * 0.6));

    return (
        <m.div
            className={cn("absolute", object.position, object.rotate, object.front ? "z-20" : "z-0")}
            style={{ x, y }}
        >
            <img
                src={`/images/coins/${object.src}.webp`}
                alt=""
                width={object.size}
                height={object.size}
                style={{
                    width: object.size,
                    height: object.size,
                    filter: object.blur ? `blur(${object.blur}px)` : undefined,
                    opacity: object.opacity,
                }}
            />
        </m.div>
    );
};

/**
 * The hero's props: a glow at the horizon and the product's 3D pieces
 * arranged in depth around the headline — some behind the copy, the
 * nearest ones in front of it. The floor they stand on is rendered by
 * StageFloor, one level up, so it can span past this section.
 */
export const HeroScene = () => {
    /**
     * Whether the visitor prefers reduced motion.
     */
    const reducedMotion = useReducedMotion();

    /**
     * The cursor's offset from the centre of the viewport, -0.5 to 0.5.
     * Raw on purpose: each piece applies its own spring to it.
     */
    const pointerX = useMotionValue(0);
    const pointerY = useMotionValue(0);

    useEffect(() => {
        if (reducedMotion) {
            return;
        }

        const handlePointerMove = (event: MouseEvent) => {
            pointerX.set(event.clientX / window.innerWidth - 0.5);
            pointerY.set(event.clientY / window.innerHeight - 0.5);
        };

        window.addEventListener("mousemove", handlePointerMove);

        return () => window.removeEventListener("mousemove", handlePointerMove);
    }, [pointerX, pointerY, reducedMotion]);

    return (
        <div aria-hidden="true" className="pointer-events-none absolute inset-0">
            {/** Horizon glow. */}
            <div className="bg-primary/20 absolute bottom-[240px] left-1/2 h-56 w-[70%] -translate-x-1/2 rounded-[100%] blur-[110px]" />

            {OBJECTS.map((object) => (
                <SceneObjectLayer key={object.src} object={object} pointerX={pointerX} pointerY={pointerY} />
            ))}
        </div>
    );
};
