<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean, default: true },
    status: { type: String, default: '' },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('admin.login'), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Admin sign in" />
    <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-6 mx-auto" style="max-width: 28rem">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-1">Staff sign in</h4>
                    <p class="mb-4 text-muted">Admin panel — not for customer accounts.</p>

                    <p v-if="status" class="alert alert-success mb-4">{{ status }}</p>

                    <form class="mb-4" @submit.prevent="submit">
                        <div class="mb-4">
                            <label for="email" class="form-label">Email</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="form-control"
                                required
                                autofocus
                                autocomplete="username"
                            >
                            <div v-if="form.errors.email" class="form-text text-danger">{{ form.errors.email }}</div>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="form-control"
                                required
                                autocomplete="current-password"
                            >
                            <div v-if="form.errors.password" class="form-text text-danger">{{ form.errors.password }}</div>
                        </div>
                        <div class="mb-4 form-check">
                            <input id="remember" v-model="form.remember" type="checkbox" class="form-check-input">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" :disabled="form.processing">
                            Sign in
                        </button>
                    </form>

                    <p class="text-center text-muted mb-0">
                        <Link v-if="canResetPassword" :href="route('password.request')" class="text-primary">Forgot password?</Link>
                        <span v-if="canResetPassword"> · </span>
                        <Link :href="route('store.home')" class="text-primary">Back to store</Link>
                    </p>
                    <p class="text-center text-muted mt-3 mb-0 small">
                        Shopping for boots?
                        <Link :href="route('login')" class="text-primary">Customer login</Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
