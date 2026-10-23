const CLAIM_STATUS_CONFIG = {
  pending: {
    label: 'Pendente',
    color: 'warning',
    icon: 'mdi-clock-outline',
  },
  accepted: {
    label: 'Aceite',
    color: 'info',
    icon: 'mdi-handshake-outline',
  },
  rejected: {
    label: 'Rejeitada',
    color: 'error',
    icon: 'mdi-close-circle-outline',
  },
  cancelled: {
    label: 'Cancelada',
    color: 'grey',
    icon: 'mdi-cancel',
  },
  completed: {
    label: 'Concluída',
    color: 'success',
    icon: 'mdi-check-circle-outline',
  },
}

export function getClaimStatusConfig(status) {
  return CLAIM_STATUS_CONFIG[status] ?? {
    label: status || 'Estado desconhecido',
    color: 'grey',
    icon: 'mdi-help-circle-outline',
  }
}

export function getClaimFlowLabels(itemType) {
  if (itemType === 'lost') {
    return {
      claimCta: 'Encontrei este objecto',
      claimTitle: 'Informar que encontrei este objecto',
      claimSubject: 'Informação de encontro',
      accept: 'Aceitar informação',
      confirm: 'Confirmar recepção',
    }
  }

  return {
    claimCta: 'Este objecto é meu',
    claimTitle: 'Reclamar este objecto',
    claimSubject: 'Reclamação de propriedade',
    accept: 'Aceitar reclamação',
    confirm: 'Confirmar devolução',
  }
}
