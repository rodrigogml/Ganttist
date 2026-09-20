import { createRouter, createWebHistory } from 'vue-router'
import HomePage from './HomePage.vue'
import { features } from './lib/features'
import ProjectPlanningPage from './ProjectPlanningPage.vue'

const ProjectDocumentsPage = () => import('./documents/ProjectDocumentsPage.vue')

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: HomePage },
    { path: '/projects/:id/tasks', name: 'project-tasks', component: ProjectPlanningPage },
    { path: '/projects/:id/gantt', name: 'project-gantt', component: ProjectPlanningPage },
    { path: '/projects/:id/documents', name: 'project-documents', component: ProjectDocumentsPage },
    { path: '/projects/:id/documents/:documentId', name: 'project-document', component: ProjectDocumentsPage },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(to => {
  if (!features.documents && ['project-documents', 'project-document'].includes(String(to.name))) {
    return { name: 'project-gantt', params: { id: to.params.id } }
  }
})
