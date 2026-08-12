export function normalizeSearch(value: string): string {
  return value
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLocaleLowerCase('pt-BR')
    .trim();
}

export function matchesSearch(
  query: string,
  values: Array<string | number>,
): boolean {
  const terms = normalizeSearch(query).split(/\s+/).filter(Boolean);

  if (terms.length === 0) {
    return true;
  }

  const searchableText = normalizeSearch(values.join(' '));

  return terms.every((term) => searchableText.includes(term));
}
