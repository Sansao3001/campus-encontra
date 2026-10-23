import { createApp } from 'vue'
import { createVuetify } from 'vuetify'
import {
  VAlert,
  VApp,
  VAppBar,
  VAppBarNavIcon,
  VAvatar,
  VBtn,
  VCard,
  VCardActions,
  VCardItem,
  VCardSubtitle,
  VCardText,
  VCardTitle,
  VChip,
  VCol,
  VContainer,
  VDivider,
  VFooter,
  VIcon,
  VList,
  VListItem,
  VListItemTitle,
  VMain,
  VNavigationDrawer,
  VRow,
  VSkeletonLoader,
  VSpacer,
  VTextField,
} from 'vuetify/components'
import * as directives from 'vuetify/directives'
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import App from './App.vue'
import router from './router'
import './styles/main.css'

const components = {
  VAlert,
  VApp,
  VAppBar,
  VAppBarNavIcon,
  VAvatar,
  VBtn,
  VCard,
  VCardActions,
  VCardItem,
  VCardSubtitle,
  VCardText,
  VCardTitle,
  VChip,
  VCol,
  VContainer,
  VDivider,
  VFooter,
  VIcon,
  VList,
  VListItem,
  VListItemTitle,
  VMain,
  VNavigationDrawer,
  VRow,
  VSkeletonLoader,
  VSpacer,
  VTextField,
}

const vuetify = createVuetify({
  components,
  directives,

  theme: {
    defaultTheme: 'campusLight',

    themes: {
      campusLight: {
        dark: false,

        colors: {
          primary: '#164E80',
          secondary: '#0B1F3A',
          'campus-light': '#DCE8F5',
          background: '#F4F7FB',
          surface: '#FFFFFF',
          info: '#164E80',
          success: '#2E7D32',
          warning: '#EF6C00',
          error: '#C62828',
          'on-background': '#263445',
          'on-surface': '#263445',
        },
      },
    },
  },
})

createApp(App)
  .use(vuetify)
  .use(router)
  .mount('#app')
