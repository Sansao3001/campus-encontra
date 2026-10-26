export const roleOptions = [
  { title: 'Estudante', value: 'student' },
  { title: 'Administrador', value: 'admin' },
]

export const accountStatusOptions = [
  { title: 'Activa', value: 'active' },
  { title: 'Suspensa', value: 'suspended' },
]

export const claimStatusOptions = [
  { title: 'Pendente', value: 'pending' },
  { title: 'Aceite', value: 'accepted' },
  { title: 'Recusada', value: 'rejected' },
  { title: 'Cancelada', value: 'cancelled' },
  { title: 'Concluída', value: 'completed' },
]

export function getRoleLabel(value) {
  return value === 'admin' ? 'Administrador' : 'Estudante'
}

export function getRoleColor(value) {
  return value === 'admin' ? 'primary' : 'secondary'
}

export function getAccountStatusLabel(user) {
  return user?.suspended_at ? 'Suspensa' : 'Activa'
}

export function getAccountStatusColor(user) {
  return user?.suspended_at ? 'error' : 'success'
}

export function getClaimStatusLabel(value) {
  return claimStatusOptions.find((item) => item.value === value)?.title || value
}

export function getClaimStatusColor(value) {
  return {
    pending: 'warning',
    accepted: 'info',
    rejected: 'error',
    cancelled: 'secondary',
    completed: 'success',
  }[value] || 'secondary'
}

export function getModerationLabel(item) {
  return item?.is_moderated ? 'Retirado' : 'Publicado'
}

export function getModerationColor(item) {
  return item?.is_moderated ? 'error' : 'success'
}

export const adminActionLabels = {
  'user.suspend': 'Conta suspensa',
  'user.reactivate': 'Conta reactivada',
  'user.role_change': 'Papel alterado',
  'item.moderate': 'Anúncio retirado',
  'item.restore': 'Anúncio reposto',
  'report.update': 'Denúncia actualizada',
}

export function getAdminActionLabel(action) {
  return adminActionLabels[action] || action
}
