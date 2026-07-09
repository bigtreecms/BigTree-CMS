## PHP Coding Style

Follow PSR-12 (https://www.php-fig.org/psr/psr-12/) coding standards with the following modifications / additions:

- Tabs instead of spaces
- There should be a blank line before and after control structures (unless they are the first or last line within a function or file)
- No single line if statements (or other control structures such as switch statements)
- A blank line should appear before a return statement if a non-control structure line precedes it
- Case statements should not have a return on the same line as the case

## React Coding Style

- Components should use interfaces for their properties rather than being defined inline.
- Components should be defined as const rather than functions.
- Components that are potentially reusable or more than a 10 lines of code should be their own file.
- Prettier formatting should be applied to all files.
- Tabs instead of spaces
- There should be a blank line before and after control structures (unless they are the first or last line within a function or file)
- No single line if statements
- A blank line should appear before a return statement if a non-control structure line precedes it
