<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<title>@yield('title', 'Dashboard') · {{ config('app.name', 'AssetKu') }}</title>
<link rel="icon" href="{{ asset('images/logo.svg') }}" type="image/svg+xml"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<script>
tailwind.config={theme:{extend:{colors:{"border-strong":"#CBD5E1","border-subtle":"#E2E8F0","surface-canvas":"#F8FAFC","surface-card":"#FFFFFF","surface-subtle":"#F1F5F9","surface-container":"#e5eeff","surface-container-low":"#eff4ff","on-surface":"#0b1c30","on-surface-variant":"#45464d","outline":"#76777d","outline-variant":"#c6c6cd","primary":"#000000","on-primary":"#ffffff","primary-container":"#131b2e","on-primary-container":"#7c839b","tertiary-container":"#0d1c2f","on-tertiary-container":"#76859b","inverse-surface":"#213145","secondary":"#904d00","secondary-container":"#fe932c","on-secondary-container":"#663500","secondary-fixed":"#ffdcc3","error":"#ba1a1a","on-error":"#ffffff","error-container":"#ffdad6","on-error-container":"#93000a","status-available":"#059669","status-assigned":"#2563EB","status-loan":"#7C3AED","status-repair":"#EA580C","status-disposal":"#DC2626","status-neutral":"#475569","status-pending":"#D97706"},
borderRadius:{DEFAULT:"0.125rem",lg:"0.25rem",xl:"0.5rem",full:"9999px"},
spacing:{gutter:"1.5rem","space-xs":"0.25rem","space-sm":"0.5rem","space-md":"0.75rem","space-lg":"1.25rem","space-xl":"2rem"},
fontFamily:{sans:["Inter","sans-serif"],mono:["JetBrains Mono","monospace"]},
fontSize:{"label-sm":["11px",{lineHeight:"14px",letterSpacing:"0.04em",fontWeight:"600"}],"label-md":["12px",{lineHeight:"16px",letterSpacing:"0.02em",fontWeight:"600"}],"body-sm":["13px",{lineHeight:"18px"}],"body-md":["14px",{lineHeight:"20px"}],"code-sm":["12px",{lineHeight:"16px",fontWeight:"500"}],"title-md":["16px",{lineHeight:"24px",letterSpacing:"-0.005em",fontWeight:"600"}],"headline-sm":["20px",{lineHeight:"28px",letterSpacing:"-0.01em",fontWeight:"600"}],"display-md":["24px",{lineHeight:"32px",letterSpacing:"-0.015em",fontWeight:"600"}],"display-lg":["32px",{lineHeight:"40px",letterSpacing:"-0.02em",fontWeight:"700"}]}}}};
</script>
<style type="text/tailwindcss">
@layer components{
 .card{@apply bg-surface-card rounded-lg border border-border-subtle shadow-[0_1px_3px_rgba(15,23,42,0.04)];}
 .card-header{@apply px-space-lg py-space-md border-b border-border-subtle flex items-center justify-between gap-space-md;}
 .card-title{@apply text-title-md text-on-surface;}
 .card-body{@apply p-space-lg;}
 .btn{@apply inline-flex items-center justify-center gap-space-xs h-9 px-space-md rounded text-label-md transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed;}
 .btn-sm{@apply h-7 px-space-sm text-label-sm;}
 .btn-primary{@apply bg-primary-container hover:bg-inverse-surface text-on-primary shadow-[0_1px_3px_rgba(15,23,42,0.12)];}
 .btn-secondary{@apply bg-surface-card border border-border-strong hover:bg-surface-subtle text-on-surface;}
 .btn-accent{@apply bg-secondary-container hover:bg-[#f0851c] text-on-secondary-container;}
 .btn-success{@apply bg-status-available hover:bg-emerald-700 text-white;}
 .btn-danger{@apply bg-status-disposal hover:bg-red-700 text-white;}
 .btn-ghost{@apply text-on-surface-variant hover:bg-surface-subtle hover:text-on-surface;}
 .form-label{@apply block text-label-md text-on-surface-variant mb-space-xs;}
 .form-input{@apply block w-full h-9 px-space-md rounded border border-border-strong bg-surface-card text-body-sm text-on-surface placeholder:text-outline focus:border-secondary focus:ring-1 focus:ring-secondary;}
 textarea.form-input{@apply h-auto py-space-sm;}
 .form-hint{@apply mt-1 text-label-sm font-normal text-outline;}
 .form-error{@apply mt-1 text-label-sm font-medium text-error;}
 .table{@apply w-full text-left text-body-sm;}
 .table thead th{@apply px-space-md py-space-sm bg-surface-subtle text-label-sm uppercase text-on-surface-variant border-b border-border-subtle whitespace-nowrap;}
 .table tbody td{@apply px-space-md py-space-sm border-b border-border-subtle align-middle;}
 .table tbody tr:hover{@apply bg-surface-container-low/60;}
 .tag{@apply font-mono text-code-sm text-on-surface;}
 .dl-grid{@apply grid grid-cols-1 sm:grid-cols-2 gap-x-space-lg gap-y-space-md;}
 .dl-grid dt{@apply text-label-sm uppercase text-outline;}
 .dl-grid dd{@apply text-body-sm text-on-surface mt-0.5;}
}
.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;font-size:20px;line-height:1;vertical-align:middle}
[x-cloak]{display:none!important}
</style>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script>jQuery.ajaxSetup({ headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest' } });</script>
<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.14.1/dist/cdn.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
