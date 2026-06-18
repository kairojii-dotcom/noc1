<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — NETVORA NOC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="login-orb" style="width:380px;height:380px;background:#16f08f;top:-80px;right:-60px"></div>
<div class="login-orb" style="width:320px;height:320px;background:#0c8f5a;bottom:-100px;left:-80px"></div>

<div class="login-wrap" x-data="loginForm()">
    <div class="login-card glass reveal">
        <div class="flex items-center gap-3" style="margin-bottom:28px">
            <div class="brand-logo" style="width:48px;height:48px;font-size:22px"><i class="fa-solid fa-circle-nodes"></i></div>
            <div>
                <div class="brand-name" style="font-size:22px">NETVORA<span> NOC</span></div>
                <div style="color:var(--muted);font-size:12.5px">ISP Management & Monitoring</div>
            </div>
        </div>

        <h1 style="font-size:24px;margin:0 0 4px">Selamat datang 👋</h1>
        <p style="color:var(--muted);font-size:14px;margin-bottom:26px">Masuk untuk mengakses dashboard NOC Anda.</p>

        <form @submit.prevent="submit">
            <div style="margin-bottom:16px">
                <label class="lbl">Email</label>
                <input type="email" class="field" placeholder="superadmin@netvora.com" x-model="email" data-testid="login-email" required>
            </div>
            <div style="margin-bottom:16px">
                <label class="lbl">Password</label>
                <div style="position:relative">
                    <input :type="show ? 'text':'password'" class="field" placeholder="••••••••" x-model="password" data-testid="login-password" required>
                    <i class="fa-solid" :class="show ? 'fa-eye-slash':'fa-eye'" @click="show=!show" style="position:absolute;right:14px;top:14px;color:var(--muted);cursor:pointer"></i>
                </div>
            </div>
            <div style="margin-bottom:22px">
                <label class="lbl">Domain Tenant <span style="color:var(--faint)">(kosongkan untuk Super Admin)</span></label>
                <input type="text" class="field" placeholder="noc.majujaya.id" x-model="domain" data-testid="login-domain">
            </div>

            <template x-if="error">
                <div class="chip offline" style="width:100%;margin-bottom:16px;justify-content:flex-start" x-text="error"></div>
            </template>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center" :disabled="loading" data-testid="login-submit">
                <span x-show="!loading"><i class="fa-solid fa-right-to-bracket"></i> Masuk</span>
                <span x-show="loading"><i class="fa-solid fa-spinner fa-spin"></i> Memproses...</span>
            </button>
        </form>

        <div style="margin-top:22px;padding-top:18px;border-top:1px solid var(--line);font-size:12.5px;color:var(--muted)">
            <i class="fa-solid fa-shield-halved text-green"></i> JWT + Refresh Token + RBAC Multi-Tenant
        </div>
    </div>
</div>

<script src="/assets/js/app.js"></script>
<script>
function loginForm() {
    return {
        email: '', password: '', domain: '', show: false, loading: false, error: '',
        async submit() {
            this.loading = true; this.error = '';
            try {
                const data = await NV.login(this.email, this.password, this.domain || null);
                location.href = (data.user.role || data.user.role_code) === 'super_admin' ? '/superadmin' : '/dashboard';
            } catch (e) { this.error = e.message; }
            finally { this.loading = false; }
        }
    };
}
document.addEventListener('alpine:init', () => {});
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</body>
</html>
