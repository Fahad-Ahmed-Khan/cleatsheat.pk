<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    seo: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head>
        <title>{{ seo.title }}</title>
        <meta v-if="seo.description" name="description" :content="seo.description" />
        <meta v-if="seo.robots" name="robots" :content="seo.robots" />
        <link v-if="seo.canonical" rel="canonical" :href="seo.canonical" />

        <meta property="og:title" :content="seo.og_title || seo.title" />
        <meta v-if="seo.og_description || seo.description" property="og:description" :content="seo.og_description || seo.description" />
        <meta v-if="seo.og_image" property="og:image" :content="seo.og_image" />
        <meta property="og:type" :content="seo.og_type || 'website'" />
        <meta v-if="seo.canonical" property="og:url" :content="seo.canonical" />
        <meta v-if="seo.og_site_name" property="og:site_name" :content="seo.og_site_name" />
        <meta property="og:locale" :content="seo.og_locale || 'en_PK'" />

        <meta name="twitter:card" :content="seo.twitter_card || 'summary_large_image'" />
        <meta v-if="seo.twitter_site" name="twitter:site" :content="seo.twitter_site" />
        <meta name="twitter:title" :content="seo.og_title || seo.title" />
        <meta v-if="seo.og_description || seo.description" name="twitter:description" :content="seo.og_description || seo.description" />
        <meta v-if="seo.og_image" name="twitter:image" :content="seo.og_image" />

        <component
            :is="'script'"
            v-if="seo.schema_json"
            type="application/ld+json"
            v-html="seo.schema_json"
        />
    </Head>
</template>
