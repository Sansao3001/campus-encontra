import test from 'node:test'
import assert from 'node:assert/strict'

import { getClaimFlowLabels, getClaimStatusConfig } from '../src/utils/claim.js'
import { getApiErrorMessage, getFieldErrors } from '../src/utils/errors.js'
import { formatDate, formatDateTime, truncate } from '../src/utils/format.js'
import { getStatusConfig, getTypeConfig } from '../src/utils/item.js'
import { getSafeRedirect } from '../src/utils/navigation.js'

test('configuração de tipos de objecto', () => {
  assert.equal(getTypeConfig('lost').label, 'Perdido')
  assert.equal(getTypeConfig('found').label, 'Encontrado')
  assert.equal(getTypeConfig('outro').color, 'grey')
})

test('configuração de estados de objecto', () => {
  assert.equal(getStatusConfig('open').label, 'Em aberto')
  assert.equal(getStatusConfig('pending').label, 'Em recuperação')
  assert.equal(getStatusConfig('returned').label, 'Devolvido')
  assert.equal(getStatusConfig('desconhecido').label, 'desconhecido')
})

test('configuração de estados de reclamação', () => {
  assert.equal(getClaimStatusConfig('accepted').label, 'Aceite')
  assert.equal(getClaimStatusConfig('completed').label, 'Concluída')
  assert.equal(getClaimStatusConfig('x').color, 'grey')
})

test('fluxo de reclamação distingue perdidos de encontrados', () => {
  const lost = getClaimFlowLabels('lost')
  const found = getClaimFlowLabels('found')

  assert.equal(lost.claimCta, 'Encontrei este objecto')
  assert.equal(lost.confirm, 'Confirmar recepção')
  assert.equal(found.claimCta, 'Este objecto é meu')
  assert.equal(found.confirm, 'Confirmar devolução')
})

test('getSafeRedirect só aceita caminhos internos', () => {
  assert.equal(getSafeRedirect('/app/objectos/1'), '/app/objectos/1')
  assert.equal(getSafeRedirect('/entrar?redirect=/app'), '/entrar?redirect=/app')
  assert.equal(getSafeRedirect('//evil.example'), null)
  assert.equal(getSafeRedirect('https://evil.example'), null)
  assert.equal(getSafeRedirect('/caminho\\inseguro'), null)
  assert.equal(getSafeRedirect('/caminho\u0000'), null)
  assert.equal(getSafeRedirect(null, '/inicio'), '/inicio')
})

test('getApiErrorMessage prioriza mensagem da API', () => {
  assert.equal(
    getApiErrorMessage({ response: { data: { message: 'Erro claro.' } } }),
    'Erro claro.',
  )

  assert.equal(
    getApiErrorMessage(
      { response: { data: { errors: { title: ['Título inválido.'] } } } },
      'fallback',
    ),
    'Título inválido.',
  )

  assert.equal(
    getApiErrorMessage({ code: 'ECONNABORTED' }),
    'O servidor demorou demasiado tempo a responder. Tenta novamente.',
  )

  assert.equal(
    getApiErrorMessage({}),
    'Não foi possível contactar a API. Confirma se o Laravel está ligado.',
  )

  assert.equal(getApiErrorMessage({ response: { status: 500 } }, 'Falhou.'), 'Falhou.')
})

test('getFieldErrors devolve o primeiro erro de cada campo', () => {
  const errors = getFieldErrors({
    response: {
      data: {
        errors: {
          title: ['Obrigatório.'],
          photo: ['Demasiado grande.'],
        },
      },
    },
  })

  assert.deepEqual(errors, {
    title: 'Obrigatório.',
    photo: 'Demasiado grande.',
  })

  assert.deepEqual(getFieldErrors({}), {})
})

test('formatadores lidam com valores em falta', () => {
  assert.equal(formatDate(null), 'Data não indicada')
  assert.equal(formatDateTime('data-inválida'), 'Data não indicada')
  assert.equal(truncate('', 10), '')
  assert.equal(truncate('abcdef', 3), 'abc…')
  assert.equal(truncate('abc', 10), 'abc')
})
