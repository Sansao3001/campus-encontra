import test from 'node:test'
import assert from 'node:assert/strict'

import {
  getAccountStatusColor,
  getAccountStatusLabel,
  getAdminActionLabel,
  getClaimStatusColor,
  getClaimStatusLabel,
  getModerationColor,
  getModerationLabel,
  getRoleColor,
  getRoleLabel,
} from '../src/utils/admin.js'
import {
  getReportReasonLabel,
  getReportStatusColor,
  getReportStatusLabel,
  getReportTargetLabel,
} from '../src/utils/report.js'

test('rótulos de papéis e estados de conta', () => {
  assert.equal(getRoleLabel('admin'), 'Administrador')
  assert.equal(getRoleLabel('student'), 'Estudante')
  assert.equal(getRoleColor('admin'), 'primary')
  assert.equal(getAccountStatusLabel({ suspended_at: null }), 'Activa')
  assert.equal(getAccountStatusLabel({ suspended_at: '2026-10-26' }), 'Suspensa')
  assert.equal(getAccountStatusColor({ suspended_at: null }), 'success')
  assert.equal(getAccountStatusColor({ suspended_at: '2026-10-26' }), 'error')
})

test('rótulos e cores de solicitações', () => {
  assert.equal(getClaimStatusLabel('pending'), 'Pendente')
  assert.equal(getClaimStatusLabel('completed'), 'Concluída')
  assert.equal(getClaimStatusColor('rejected'), 'error')
  assert.equal(getClaimStatusColor('desconhecido'), 'secondary')
})

test('estado de moderação de anúncios', () => {
  assert.equal(getModerationLabel({ is_moderated: false }), 'Publicado')
  assert.equal(getModerationLabel({ is_moderated: true }), 'Retirado')
  assert.equal(getModerationColor({ is_moderated: false }), 'success')
  assert.equal(getModerationColor({ is_moderated: true }), 'error')
})

test('rótulos de auditoria e denúncias', () => {
  assert.equal(getAdminActionLabel('user.suspend'), 'Conta suspensa')
  assert.equal(getAdminActionLabel('acao.desconhecida'), 'acao.desconhecida')
  assert.equal(getReportReasonLabel('harassment'), 'Assédio ou comportamento abusivo')
  assert.equal(getReportStatusLabel('reviewing'), 'Em análise')
  assert.equal(getReportStatusColor('resolved'), 'success')
  assert.equal(
    getReportTargetLabel({
      target_type: 'item',
      item: { title: 'Mochila' },
    }),
    'Anúncio: Mochila',
  )
  assert.equal(
    getReportTargetLabel({
      target_type: 'user',
      reported_user: { name: 'Ana' },
    }),
    'Utilizador: Ana',
  )
})
