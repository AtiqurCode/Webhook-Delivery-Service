<script setup>
import { RouterLink, RouterView } from 'vue-router'
import ToastContainer from '@/components/ToastContainer.vue'
</script>

<template>
  <header>
    <RouterLink to="/" class="brand">Webhook Delivery Service</RouterLink>
    <nav>
      <RouterLink to="/">Dashboard</RouterLink>
      <RouterLink to="/webhooks">Webhooks</RouterLink>
      <RouterLink to="/events/new">Create Event</RouterLink>
      <RouterLink to="/deliveries">Delivery History</RouterLink>
    </nav>
  </header>

  <main>
    <RouterView />
  </main>

  <ToastContainer />
</template>

<style scoped>
header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  padding-bottom: 1.5rem;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid var(--color-border);
}

.brand {
  font-weight: 700;
  font-size: 1.1rem;
  opacity: 1 !important;
  color: var(--color-heading) !important;
}

nav {
  display: flex;
  gap: 1.5rem;
}

nav a {
  font-size: 0.9rem;
  font-weight: 500;
  opacity: 0.75;
}

nav a.router-link-active {
  opacity: 1;
  color: var(--color-heading);
}
</style>
