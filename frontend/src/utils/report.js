export const reportReasonOptions = [
  { title: 'Conteúdo inadequado', value: 'inappropriate' },
  { title: 'Spam ou publicidade', value: 'spam' },
  { title: 'Assédio ou comportamento abusivo', value: 'harassment' },
  { title: 'Suspeita de fraude', value: 'fraud' },
  { title: 'Outro motivo', value: 'other' },
]

export const reportStatusOptions = [
  { title: 'Pendente', value: 'pending' },
  { title: 'Em análise', value: 'reviewing' },
  { title: 'Resolvida', value: 'resolved' },
  { title: 'Arquivada', value: 'dismissed' },
]

export function getReportReasonLabel(value) {
  return reportReasonOptions.find((item) => item.value === value)?.title || value
}

export function getReportStatusLabel(value) {
  return reportStatusOptions.find((item) => item.value === value)?.title || value
}

export function getReportTargetLabel(report) {
  if (report?.target_type === 'user') {
    return report.reported_user?.name
      ? `Utilizador: ${report.reported_user.name}`
      : 'Comportamento de utilizador'
  }

  return report?.item?.title
    ? `Anúncio: ${report.item.title}`
    : 'Anúncio'
}

export function getReportStatusColor(value) {
  return {
    pending: 'warning',
    reviewing: 'info',
    resolved: 'success',
    dismissed: 'secondary',
  }[value] || 'secondary'
}
