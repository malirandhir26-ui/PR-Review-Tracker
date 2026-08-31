/**
 * Turbo C Graphics Compatibility Header Stub for Modern GCC/G++
 * Note: Modern systems do not use BGI graphics drivers. For full GUI graphics,
 * consider using SDL2 or Raylib. This stub provides basic definitions.
 */

#ifndef TURBOC_GRAPHICS_H
#define TURBOC_GRAPHICS_H

#include <stdio.h>
#include <stdlib.h>

// Graphics drivers and modes
#define DETECT 0
#define VGA 9
#define VGAHI 2

enum COLORS_G {
    BLACK, BLUE, GREEN, CYAN, RED, MAGENTA, BROWN,
    LIGHTGRAY, DARKGRAY, LIGHTBLUE, LIGHTGREEN, LIGHTCYAN,
    LIGHTRED, LIGHTMAGENTA, YELLOW, WHITE
};

inline void initgraph(int *graphdriver, int *graphmode, const char *pathtodriver) {
    (void)graphdriver;
    (void)graphmode;
    (void)pathtodriver;
    fprintf(stderr, "[TurboC Compat] initgraph() called. Note: BGI graphics are emulated as text-mode or require SDL/X11 on modern Linux.\n");
}

inline void closegraph(void) {
    printf("[TurboC Compat] closegraph() called.\n");
}

inline void line(int x1, int y1, int x2, int y2) {
    printf("[TurboC Compat] line(%d, %d, %d, %d)\n", x1, y1, x2, y2);
}

inline void rectangle(int left, int top, int right, int bottom) {
    printf("[TurboC Compat] rectangle(%d, %d, %d, %d)\n", left, top, right, bottom);
}

inline void circle(int x, int y, int radius) {
    printf("[TurboC Compat] circle(%d, %d, %d)\n", x, y, radius);
}

inline void setcolor(int color) {
    (void)color;
}

inline void outtextxy(int x, int y, char *textstring) {
    printf("[TurboC Compat] outtextxy(%d, %d, %s)\n", x, y, textstring);
}

#endif // TURBOC_GRAPHICS_H
