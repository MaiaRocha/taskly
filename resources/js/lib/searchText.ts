/**
 * Case-insensitive AND accent-insensitive normalization for free-text search
 * (e.g. "reuniao" must match "Reunião"). `NFD` decomposes accented
 * characters into a base letter + a combining diacritical mark, which the
 * regex below then strips (Unicode range U+0300-U+036F, "Combining
 * Diacritical Marks") — no external library needed.
 */
export function normalizeSearchText(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('pt-BR')
        .trim();
}
