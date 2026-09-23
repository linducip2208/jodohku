@php $faqItems = app(\App\Services\FaqService::class)->homepage(); @endphp
<x-faq-list :items="$faqItems" />
