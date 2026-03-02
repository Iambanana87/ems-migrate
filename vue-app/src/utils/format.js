// utils/format.js
// Extracted formatting logic from massive legacy app.js

export function formatCycle(seconds) {
  if (seconds === null || seconds === undefined) return '--'
  const m = Math.floor(seconds / 60)
  const s = Math.round(seconds % 60)
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

export function formatPercent(value, decimals = 1) {
  if (value === null || value === undefined) return '--'
  return `${Number(value).toFixed(decimals)}%`
}

export function formatNumber(value) {
  if (value === null || value === undefined) return '--'
  return Number(value).toLocaleString()
}

export function effClass(eff, eff_ll) {
  if (eff === null || eff === undefined) return ''
  return eff < eff_ll ? 'text-red-600' : 'text-green-600'
}

export function statusCardClass(status) {
  switch (status) {
    case 'breached':     return 'border-red-400 bg-red-50'
    case 'disconnected': return 'border-gray-300 bg-gray-50 opacity-70'
    default:             return 'border-gray-200 bg-white'
  }
}
