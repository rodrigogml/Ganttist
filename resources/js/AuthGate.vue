<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{ auth: { sending: boolean; sent: boolean; error: string; requestLink: (email: string) => Promise<void>; verifyPin: (pin: string) => Promise<void>; resetSent: () => void } }>()
const email = ref('')
const pin = ref('')
function submit() { if (email.value) props.auth.requestLink(email.value) }
</script>

<template>
  <main class="auth-page">
    <section class="auth-card">
      <div class="auth-mark">G</div>
      <p class="eyebrow">PLANEJAMENTO COM CLAREZA</p>
      <h1>Entre no Ganttist</h1>
      <p v-if="!auth.sent" class="auth-copy">Informe seu e-mail e enviaremos um link seguro para acessar seu espaço de trabalho.</p>
      <template v-if="auth.sent">
        <p class="auth-copy">Se o endereço puder ser utilizado, enviamos um link de acesso. Verifique sua caixa de entrada e a pasta de spam.</p>
        <form @submit.prevent="auth.verifyPin(pin)">
          <label class="auth-label">Código de 6 dígitos<input v-model="pin" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required></label>
          <button class="auth-submit" :disabled="auth.sending || pin.length !== 6">{{ auth.sending ? 'Validando…' : 'Entrar com código' }}</button>
        </form>
        <button class="auth-secondary" @click="auth.resetSent">Usar outro e-mail</button>
      </template>
      <form v-else @submit.prevent="submit">
        <label class="auth-label">E-mail<input v-model="email" type="email" autocomplete="email" placeholder="voce@exemplo.com" required></label>
        <button class="auth-submit" :disabled="auth.sending">{{ auth.sending ? 'Enviando…' : 'Enviar link de acesso' }}</button>
      </form>
      <p v-if="auth.error" class="auth-error">{{ auth.error }}</p>
    </section>
  </main>
</template>
