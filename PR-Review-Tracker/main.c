#include <stdio.h>
#include <conio.h>

int main() {
    clrscr();
    printf("=== Eclipse-Style C/C++ IDE ===\n");
    printf("Enter your lucky number: ");
    int num;
    scanf("%d", &num);
    printf("Your lucky number is: %d\n", num);
    printf("Press any key to exit...\n");
    getch();
    return 0;
}
