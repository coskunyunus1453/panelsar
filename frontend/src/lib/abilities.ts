/** API'den gelen kullanıcı yetenekleri (Sanctum abilities ile aynı isimler). */
export function tokenHasAbility(
  abilities: string[] | undefined,
  required: string | null,
): boolean {
  if (required === null) {
    return true
  }
  if (!abilities?.length) {
    return false
  }
  if (abilities.includes('*')) {
    return true
  }
  return abilities.includes(required)
}
