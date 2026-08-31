#!/usr/bin/env python3
"""
Eclipse-Style Dark C/C++ IDE & Compiler Environment (turboc.py)
Features:
- Dark Theme (Eclipse-inspired dark mode)
- Split Pane Layout: Project File Explorer (Left), Code Editor (Center), Console/Output & Problems Window (Bottom)
- Auto-Completion / Suggestions popup as you type
- Dedicated separate Console window for program execution output
- Advanced editing, debugging options, and Turbo C compatibility headers
"""

import os
import sys
import subprocess
import curses
import tempfile
import glob

VERSION = "2.0.1-EclipseDark"
COMPAT_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "turboc_compat")

DEFAULT_CODE = """#include <stdio.h>
#include <conio.h>

int main() {
    clrscr();
    printf("=== Eclipse-Style C/C++ IDE ===\\n");
    printf("Enter your lucky number: ");
    int num;
    scanf("%d", &num);
    printf("Your lucky number is: %d\\n", num);
    printf("Press any key to exit...\\n");
    getch();
    return 0;
}
"""

C_KEYWORDS = [
    "printf", "scanf", "include", "define", "int", "float", "double", "char",
    "void", "return", "if", "else", "for", "while", "do", "switch", "case",
    "break", "continue", "struct", "typedef", "sizeof", "clrscr", "getch",
    "textcolor", "textbackground", "gotoxy", "iostream", "cout", "cin", "vector"
]

class EclipseIDE:
    def __init__(self, stdscr):
        self.stdscr = stdscr
        try:
            curses.curs_set(1)
        except Exception:
            pass
        self.init_colors()
        
        self.current_dir = os.getcwd()
        self.filename = "main.c"
        if not os.path.exists(self.filename):
            try:
                with open(self.filename, "w") as f:
                    f.write(DEFAULT_CODE)
            except Exception:
                pass
                
        self.load_file(self.filename)
        
        self.cursor_y = 0
        self.cursor_x = 0
        self.scroll_y = 0
        self.scroll_x = 0
        
        self.active_tab = 0
        self.console_output = ["Console initialized. Ready to build and run."]
        self.compiler_problems = ["No compilation errors."]
        
        self.file_list = []
        self.refresh_file_list()
        self.selected_file_idx = 0
        self.focus = 1
        
        self.show_suggestions = False
        self.suggestion_list = []
        self.suggestion_idx = 0
        
        self.status_message = " F2:Save F9:Compile ^F9:Run F3:Explorer Tab:SwitchPane F1:Help"

    def init_colors(self):
        try:
            curses.start_color()
            curses.use_default_colors()
            curses.init_pair(1, curses.COLOR_WHITE, curses.COLOR_BLACK)
            curses.init_pair(2, curses.COLOR_CYAN, curses.COLOR_BLACK)
            curses.init_pair(3, curses.COLOR_BLACK, curses.COLOR_CYAN)
            curses.init_pair(4, curses.COLOR_GREEN, curses.COLOR_BLACK)
            curses.init_pair(5, curses.COLOR_YELLOW, curses.COLOR_BLACK)
            curses.init_pair(6, curses.COLOR_RED, curses.COLOR_BLACK)
            curses.init_pair(7, curses.COLOR_BLACK, curses.COLOR_WHITE)
        except Exception:
            pass

    def load_file(self, fname):
        self.filename = fname
        if os.path.exists(fname):
            try:
                with open(fname, "r") as f:
                    content = f.read()
                    self.lines = content.splitlines() or [""]
            except Exception:
                self.lines = [""]
        else:
            self.lines = [""]
        self.cursor_y = 0
        self.cursor_x = 0
        self.scroll_y = 0
        self.modified = False

    def refresh_file_list(self):
        try:
            all_files = os.listdir(self.current_dir)
            self.file_list = [f for f in all_files if not f.startswith('.')]
            self.file_list.sort()
        except Exception:
            self.file_list = []

    def draw_screen(self):
        try:
            h, w = self.stdscr.getmaxyx()
            if h < 15 or w < 60:
                self.stdscr.clear()
                self.stdscr.addstr(0, 0, "Terminal too small. Min size 60x15 required.")
                self.stdscr.refresh()
                return

            self.stdscr.clear()

            # Top Menu
            self.stdscr.attron(curses.color_pair(3))
            self.stdscr.hline(0, 0, ord(' '), w)
            menu_str = f" File  Edit  Source  Refactor  Navigate  Search  Project  Run  Debug  Window  Help [{VERSION}]"
            self.stdscr.addstr(0, 0, menu_str[:w], curses.color_pair(3))
            self.stdscr.attroff(curses.color_pair(3))

            sidebar_w = 22
            bottom_h = 8
            editor_h = h - 2 - bottom_h - 1
            editor_w = w - sidebar_w

            # Sidebar Explorer
            self.stdscr.attron(curses.color_pair(2))
            self.stdscr.vline(1, sidebar_w, curses.ACS_VLINE, editor_h)
            self.stdscr.attroff(curses.color_pair(2))

            self.stdscr.addstr(1, 1, " Project Explorer ", curses.color_pair(3) | curses.A_BOLD)
            for idx, fname in enumerate(self.file_list[:editor_h - 2]):
                prefix = "> " if idx == self.selected_file_idx else "  - "
                display_name = (prefix + fname)[:sidebar_w - 2]
                attr = curses.color_pair(4) | (curses.A_REVERSE if (self.focus == 0 and idx == self.selected_file_idx) else 0)
                self.stdscr.addstr(2 + idx, 1, display_name.ljust(sidebar_w - 2), attr)

            # Editor Area
            editor_start_x = sidebar_w + 1
            for y in range(1, editor_h + 1):
                self.stdscr.addstr(y, editor_start_x, " " * (editor_w - 1), curses.color_pair(1))

            max_visible_lines = editor_h - 1
            for i in range(max_visible_lines):
                line_idx = self.scroll_y + i
                if line_idx < len(self.lines):
                    line_num_str = f"{line_idx+1:4d} | "
                    line_content = self.lines[line_idx]
                    display_str = (line_num_str + line_content)[:editor_w - 1]
                    attr = curses.color_pair(1)
                    if self.focus == 1 and line_idx == self.cursor_y:
                        attr |= curses.A_BOLD
                    self.stdscr.addstr(1 + i, editor_start_x, display_str, attr)

            # Bottom Panel
            panel_top = h - bottom_h - 1
            self.stdscr.attron(curses.color_pair(2))
            self.stdscr.hline(panel_top, 0, ord('-'), w)
            tabs_str = f" [1: Console (Output)]   [2: Problems / Errors]   (Tab to switch, F9: Build, ^F9: Run)"
            self.stdscr.addstr(panel_top, 2, tabs_str[:w-4], curses.color_pair(3) if self.focus == 2 else curses.color_pair(2))
            self.stdscr.attroff(curses.color_pair(2))

            content_lines = self.console_output if self.active_tab == 0 else self.compiler_problems
            for i in range(bottom_h - 1):
                row = panel_top + 1 + i
                if i < len(content_lines):
                    msg = content_lines[-(bottom_h - 1) + i] if len(content_lines) >= bottom_h - 1 else content_lines[i]
                    color = curses.color_pair(5) if self.active_tab == 0 else curses.color_pair(6)
                    self.stdscr.addstr(row, 1, msg[:w-2], color)

            # Autocomplete popup
            if self.show_suggestions and self.suggestion_list:
                pop_h = min(len(self.suggestion_list) + 2, 8)
                pop_w = 24
                pop_y = min(1 + (self.cursor_y - self.scroll_y) + 1, editor_h - pop_h)
                pop_x = min(editor_start_x + 8 + self.cursor_x, w - pop_w)
                
                for py in range(pop_h):
                    self.stdscr.addstr(pop_y + py, pop_x, " " * pop_w, curses.color_pair(7))
                self.stdscr.attron(curses.color_pair(7) | curses.A_BOLD)
                self.stdscr.addstr(pop_y, pop_x, " Suggestions ".center(pop_w, '-'))
                for sidx, sug in enumerate(self.suggestion_list[:pop_h - 2]):
                    prefix = ">" if sidx == self.suggestion_idx else " "
                    self.stdscr.addstr(pop_y + 1 + sidx, pop_x, f"{prefix} {sug}".ljust(pop_w))
                self.stdscr.attroff(curses.color_pair(7) | curses.A_BOLD)

            # Status bar
            self.stdscr.attron(curses.color_pair(3))
            self.stdscr.hline(h - 1, 0, ord(' '), w)
            status = f" File: {self.filename} | Ln {self.cursor_y+1}, Col {self.cursor_x+1} | Focus: {['Explorer','Editor','Console'][self.focus]} | {self.status_message}"
            self.stdscr.addstr(h - 1, 0, status[:w], curses.color_pair(3))
            self.stdscr.attroff(curses.color_pair(3))

            if self.focus == 1:
                screen_y = 1 + (self.cursor_y - self.scroll_y)
                screen_x = editor_start_x + 7 + self.cursor_x
                if 1 <= screen_y <= editor_h and editor_start_x <= screen_x < w:
                    self.stdscr.move(screen_y, screen_x)
        except Exception:
            pass

    def update_suggestions(self):
        if self.cursor_y < len(self.lines):
            line = self.lines[self.cursor_y]
            current_word = ""
            i = self.cursor_x - 1
            while i >= 0 and (line[i].isalnum() or line[i] == '_'):
                current_word = line[i] + current_word
                i -= 1
            
            if len(current_word) >= 1:
                matches = [kw for kw in C_KEYWORDS if kw.startswith(current_word)]
                if matches and matches != [current_word]:
                    self.suggestion_list = matches
                    self.show_suggestions = True
                    self.suggestion_idx = 0
                    return
        self.show_suggestions = False

    def compile_code(self):
        self.save_current_file()
        self.compiler_problems = [f"Building {self.filename}..."]
        
        ext = os.path.splitext(self.filename)[1].lower()
        compiler = "g++" if ext in [".cpp", ".cc", ".cxx"] else "gcc"
        
        self.compiled_binary = tempfile.mktemp(prefix="eclipse_bin_")
        cmd = [compiler, self.filename, "-I", COMPAT_DIR, "-o", self.compiled_binary, "-Wall", "-g"]
        
        try:
            res = subprocess.run(cmd, capture_output=True, text=True, timeout=10)
            if res.returncode == 0:
                self.compiler_problems.append("Build Successful! (0 errors, 0 warnings)")
                self.console_output.append(f"[{self.filename}] Compiled successfully.")
                return True
            else:
                self.compiler_problems.append("Build Failed with errors:")
                for err in res.stderr.splitlines():
                    self.compiler_problems.append(err)
                return False
        except Exception as e:
            self.compiler_problems.append(f"Build exception: {str(e)}")
            return False

    def run_program(self):
        success = self.compile_code()
        if not success:
            self.active_tab = 1
            return
        
        self.active_tab = 0
        self.console_output.append(f"--- Executing {self.filename} ---")
        
        try:
            res = subprocess.run([self.compiled_binary], capture_output=True, text=True, timeout=5)
            for line in res.stdout.splitlines():
                self.console_output.append(line)
            if res.stderr:
                for line in res.stderr.splitlines():
                    self.console_output.append(f"[ERR] {line}")
            self.console_output.append(f"--- Process finished with exit code {res.returncode} ---")
        except subprocess.TimeoutExpired:
            self.console_output.append("[Error] Program timed out waiting for input.")
            curses.endwin()
            print(f"\n--- Running interactively: {self.filename} ---")
            subprocess.run([self.compiled_binary])
            input("\nPress Enter to return to IDE...")
            self.stdscr.refresh()
        except Exception as e:
            self.console_output.append(f"[Execution Error] {str(e)}")

    def save_current_file(self):
        try:
            with open(self.filename, "w") as f:
                f.write("\n".join(self.lines))
            self.modified = False
            self.console_output.append(f"Saved {self.filename}")
        except Exception as e:
            self.console_output.append(f"Save error: {e}")

    def show_help_dialog(self):
        h, w = self.stdscr.getmaxyx()
        win = curses.newwin(16, 64, h // 2 - 8, w // 2 - 32)
        win.bkgd(curses.color_pair(7))
        win.box()
        win.addstr(1, 2, " Eclipse Dark C/C++ IDE - Help & Guide ", curses.A_BOLD)
        win.addstr(3, 2, " F3        : Toggle Project File Explorer sidebar")
        win.addstr(4, 2, " Tab       : Switch focus (Explorer -> Editor -> Console)")
        win.addstr(5, 2, " F2        : Save current file")
        win.addstr(6, 2, " F9        : Build / Compile C/C++ source (gcc/g++)")
        win.addstr(7, 2, " Ctrl+F9   : Run program & show output in separate Console window")
        win.addstr(8, 2, " 1 / 2     : Switch bottom tabs (Console Output vs Compiler Problems)")
        win.addstr(9, 2, " Alt+X     : Exit IDE")
        win.addstr(11, 2, " Features: Eclipse Dark UI, Auto-completion, Conio support")
        win.addstr(13, 2, " Press any key to close...")
        win.refresh()
        win.getch()

    def main_loop(self):
        while True:
            self.draw_screen()
            ch = self.stdscr.getch()
            h, w = self.stdscr.getmaxyx()
            editor_h = h - 11

            if ch == 27:
                self.stdscr.nodelay(True)
                next_ch = self.stdscr.getch()
                self.stdscr.nodelay(False)
                if next_ch in [ord('x'), ord('X')]:
                    break
                elif next_ch == curses.KEY_F9:
                    self.compile_code()
                continue

            elif ch == curses.KEY_F2:
                self.save_current_file()
            elif ch == curses.KEY_F3:
                self.focus = 1 if self.focus == 0 else 0
            elif ch == curses.KEY_F9:
                self.compile_code()
            elif ch in [curses.KEY_F10, 24]:
                self.run_program()
            elif ch == curses.KEY_F1:
                self.show_help_dialog()
            elif ch == 9:
                self.focus = (self.focus + 1) % 3
            elif ch == ord('1') and self.focus == 2:
                self.active_tab = 0
            elif ch == ord('2') and self.focus == 2:
                self.active_tab = 1

            if self.focus == 0:
                if ch == curses.KEY_UP and self.selected_file_idx > 0:
                    self.selected_file_idx -= 1
                elif ch == curses.KEY_DOWN and self.selected_file_idx < len(self.file_list) - 1:
                    self.selected_file_idx += 1
                elif ch == 10:
                    if self.file_list:
                        fname = self.file_list[self.selected_file_idx]
                        if os.path.isfile(fname):
                            self.load_file(fname)
                            self.focus = 1

            elif self.focus == 1:
                if self.show_suggestions:
                    if ch == curses.KEY_UP:
                        self.suggestion_idx = max(0, self.suggestion_idx - 1)
                        continue
                    elif ch == curses.KEY_DOWN:
                        self.suggestion_idx = min(len(self.suggestion_list) - 1, self.suggestion_idx + 1)
                        continue
                    elif ch in [10, 9]:
                        if self.suggestion_list:
                            sug = self.suggestion_list[self.suggestion_idx]
                            line = self.lines[self.cursor_y]
                            self.lines[self.cursor_y] = line[:self.cursor_x] + sug + line[self.cursor_x:]
                            self.cursor_x += len(sug)
                            self.show_suggestions = False
                            continue

                if ch == curses.KEY_UP:
                    if self.cursor_y > 0:
                        self.cursor_y -= 1
                        self.cursor_x = min(self.cursor_x, len(self.lines[self.cursor_y]))
                        if self.cursor_y < self.scroll_y:
                            self.scroll_y = self.cursor_y
                elif ch == curses.KEY_DOWN:
                    if self.cursor_y < len(self.lines) - 1:
                        self.cursor_y += 1
                        self.cursor_x = min(self.cursor_x, len(self.lines[self.cursor_y]))
                        if self.cursor_y >= self.scroll_y + editor_h:
                            self.scroll_y = self.cursor_y - editor_h + 1
                elif ch == curses.KEY_LEFT:
                    if self.cursor_x > 0:
                        self.cursor_x -= 1
                    elif self.cursor_y > 0:
                        self.cursor_y -= 1
                        self.cursor_x = len(self.lines[self.cursor_y])
                        if self.cursor_y < self.scroll_y:
                            self.scroll_y = self.cursor_y
                elif ch == curses.KEY_RIGHT:
                    if self.cursor_x < len(self.lines[self.cursor_y]):
                        self.cursor_x += 1
                    elif self.cursor_y < len(self.lines) - 1:
                        self.cursor_y += 1
                        self.cursor_x = 0
                        if self.cursor_y >= self.scroll_y + editor_h:
                            self.scroll_y = self.cursor_y - editor_h + 1
                elif ch in [curses.KEY_BACKSPACE, 127, 8]:
                    if self.cursor_x > 0:
                        line = self.lines[self.cursor_y]
                        self.lines[self.cursor_y] = line[:self.cursor_x - 1] + line[self.cursor_x:]
                        self.cursor_x -= 1
                        self.modified = True
                    elif self.cursor_y > 0:
                        prev_len = len(self.lines[self.cursor_y - 1])
                        self.lines[self.cursor_y - 1] += self.lines[self.cursor_y]
                        del self.lines[self.cursor_y]
                        self.cursor_y -= 1
                        self.cursor_x = prev_len
                        self.modified = True
                    self.update_suggestions()
                elif ch == 10:
                    line = self.lines[self.cursor_y]
                    left = line[:self.cursor_x]
                    right = line[self.cursor_x:]
                    self.lines[self.cursor_y] = left
                    self.lines.insert(self.cursor_y + 1, right)
                    self.cursor_y += 1
                    self.cursor_x = 0
                    self.modified = True
                    if self.cursor_y >= self.scroll_y + editor_h:
                        self.scroll_y += 1
                    self.show_suggestions = False
                elif 32 <= ch <= 126:
                    line = self.lines[self.cursor_y]
                    self.lines[self.cursor_y] = line[:self.cursor_x] + chr(ch) + line[self.cursor_x:]
                    self.cursor_x += 1
                    self.modified = True
                    self.update_suggestions()

def main(stdscr):
    ide = EclipseIDE(stdscr)
    ide.main_loop()

if __name__ == "__main__":
    os.environ.setdefault("TERM", "xterm-256color")
    if not os.path.exists(COMPAT_DIR):
        print(f"Error: Compat directory {COMPAT_DIR} missing.")
        sys.exit(1)
    try:
        curses.wrapper(main)
    except Exception as e:
        print(f"Terminal error: {e}")
        print("Please ensure you are running inside a standard terminal (e.g. bash/zsh).")
        sys.exit(1)
