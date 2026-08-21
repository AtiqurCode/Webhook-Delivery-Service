import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'
import WebhooksView from '../views/WebhooksView.vue'
import CreateEventView from '../views/CreateEventView.vue'
import DeliveriesView from '../views/DeliveriesView.vue'
import DeliveryDetailView from '../views/DeliveryDetailView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'dashboard',
      component: DashboardView,
      meta: { title: 'Dashboard' },
    },
    {
      path: '/webhooks',
      name: 'webhooks',
      component: WebhooksView,
      meta: { title: 'Webhooks' },
    },
    {
      path: '/events/new',
      name: 'events.new',
      component: CreateEventView,
      meta: { title: 'Create Event' },
    },
    {
      path: '/deliveries',
      name: 'deliveries',
      component: DeliveriesView,
      meta: { title: 'Delivery History' },
    },
    {
      path: '/deliveries/:id',
      name: 'deliveries.show',
      component: DeliveryDetailView,
      meta: { title: 'Delivery Details' },
    },
  ],
})

router.afterEach((to) => {
  document.title = to.meta?.title ? `${to.meta.title} · Webhook Delivery Service` : 'Webhook Delivery Service'
})

export default router
