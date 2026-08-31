/**
 * Turbo C / C++ Compatibility Header for Modern GCC/G++ (Linux/macOS)
 * Emulates classic conio.h functions (clrscr, getch, gotoxy, textcolor, kbhit, etc.)
 * using ANSI escape sequences and POSIX termios.
 */

#ifndef TURBOC_CONIO_H
#define TURBOC_CONIO_H

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <termios.h>
#include <sys/select.h>
#include <time.h>

// Turbo C Color Constants
enum COLORS {
    BLACK = 0,
    BLUE = 1,
    GREEN = 2,
    CYAN = 3,
    RED = 4,
    MAGENTA = 5,
    BROWN = 6,
    LIGHTGRAY = 7,
    DARKGRAY = 8,
    LIGHTBLUE = 9,
    LIGHTGREEN = 10,
    LIGHTCYAN = 11,
    LIGHTRED = 12,
    LIGHTMAGENTA = 13,
    YELLOW = 14,
    WHITE = 15
};

// Static helper to configure raw terminal mode for getch/kbhit
static struct termios _tc_orig_termios;
static int _tc_raw_inited = 0;

static inline void _tc_reset_terminal(void) {
    if (_tc_raw_inited) {
        tcsetattr(STDIN_FILENO, TCSANOW, &_tc_orig_termios);
        _tc_raw_inited = 0;
    }
}

static inline void _tc_set_raw_terminal(void) {
    if (!_tc_raw_inited) {
        tcgetattr(STDIN_FILENO, &_tc_orig_termios);
        struct termios raw = _tc_orig_termios;
        raw.c_lflag &= ~(ECHO | ICANON);
        tcsetattr(STDIN_FILENO, TCSANOW, &raw);
        _tc_raw_inited = 1;
        atexit(_tc_reset_terminal);
    }
}

// Clear screen
static inline void clrscr(void) {
    printf("\033[2J\033[H");
    fflush(stdout);
}

// Move cursor to (x, y) - 1-indexed as in Turbo C
static inline void gotoxy(int x, int y) {
    printf("\033[%d;%dH", y, x);
    fflush(stdout);
}

// Get character without echo
static inline int getch(void) {
    _tc_set_raw_terminal();
    int ch = getchar();
    return ch;
}

// Get character with echo
static inline int getche(void) {
    _tc_reset_terminal();
    int ch = getchar();
    _tc_set_raw_terminal();
    return ch;
}

// Check if key is hit
static inline int kbhit(void) {
    _tc_set_raw_terminal();
    struct timeval tv = {0L, 0L};
    fd_set fds;
    FD_ZERO(&fds);
    FD_SET(STDIN_FILENO, &fds);
    return select(STDIN_FILENO + 1, &fds, NULL, NULL, &tv) > 0;
}

// Text color mapping
static inline void textcolor(int color) {
    int ansi_fg;
    switch (color % 8) {
        case BLACK: ansi_fg = 30; break;
        case BLUE: ansi_fg = 34; break;
        case GREEN: ansi_fg = 32; break;
        case CYAN: ansi_fg = 36; break;
        case RED: ansi_fg = 31; break;
        case MAGENTA: ansi_fg = 35; break;
        case BROWN: ansi_fg = 33; break;
        case LIGHTGRAY: ansi_fg = 37; break;
        default: ansi_fg = 37; break;
    }
    if (color >= 8) {
        printf("\033[1;%dm", ansi_fg);
    } else {
        printf("\033[0;%dm", ansi_fg);
    }
    fflush(stdout);
}

// Text background color mapping
static inline void textbackground(int color) {
    int ansi_bg;
    switch (color % 8) {
        case BLACK: ansi_bg = 40; break;
        case BLUE: ansi_bg = 44; break;
        case GREEN: ansi_bg = 42; break;
        case CYAN: ansi_bg = 46; break;
        case RED: ansi_bg = 41; break;
        case MAGENTA: ansi_bg = 45; break;
        case BROWN: ansi_bg = 43; break;
        case LIGHTGRAY: ansi_bg = 47; break;
        default: ansi_bg = 40; break;
    }
    printf("\033[%dm", ansi_bg);
    fflush(stdout);
}

// Delay in milliseconds
static inline void delay(unsigned int milliseconds) {
    usleep(milliseconds * 1000);
}

// Sound emulation (prints beep or bell)
static inline void sound(unsigned int frequency) {
    (void)frequency;
    putchar('\a');
    fflush(stdout);
}

static inline void nosound(void) {
    // no-op on modern terminals
}

static inline void clreol(void) {
    printf("\033[K");
    fflush(stdout);
}

#endif // TURBOC_CONIO_H
