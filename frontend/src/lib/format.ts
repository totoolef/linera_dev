export const MICRO_RATIO = 100000; // 1 micro = 0.00001 €

export function microToEuro(micro: number | string): string {
  const n = typeof micro === 'string' ? Number(micro) : micro;
  return (n / MICRO_RATIO).toFixed(5) + ' €';
}

export function formatMicro(micro: number | string): string {
  const n = typeof micro === 'string' ? Number(micro) : micro;
  return n.toLocaleString('fr-FR'); // 1 234 567
}

export function dateTime(input: string | number | Date): string {
  const d = input instanceof Date ? input : new Date(input);
  // fallback si date invalide
  if (isNaN(d.getTime())) return String(input);
  return d.toLocaleString('fr-FR'); // ex: 14/08/2025 23:12:45
}
