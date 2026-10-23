const TYPE_CONFIG = {
  lost: {
    label: 'Perdido',
    color: 'deep-orange-darken-1',
    icon: 'mdi-magnify',
  },
  found: {
    label: 'Encontrado',
    color: 'success',
    icon: 'mdi-check-circle-outline',
  },
}

const STATUS_CONFIG = {
  open: {
    label: 'Em aberto',
    color: 'info',
    icon: 'mdi-clock-outline',
  },
  pending: {
    label: 'Em recuperação',
    color: 'warning',
    icon: 'mdi-handshake-outline',
  },
  returned: {
    label: 'Devolvido',
    color: 'success',
    icon: 'mdi-check-circle-outline',
  },
}

export function getTypeConfig(type) {
  return TYPE_CONFIG[type] ?? {
    label: type || 'Tipo desconhecido',
    color: 'grey',
    icon: 'mdi-help-circle-outline',
  }
}

export function getStatusConfig(status) {
  return STATUS_CONFIG[status] ?? {
    label: status || 'Estado desconhecido',
    color: 'grey',
    icon: 'mdi-help-circle-outline',
  }
}
