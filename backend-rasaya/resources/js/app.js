import './bootstrap';
import '../sass/app.scss';
import 'bootstrap';
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// Ensure brand assets are included in Vite manifest for Blade usage
// and accessible if needed at runtime
import appIconUrl from '../images/app_icon.png';
import logoHorizontalUrl from '../images/logo_horizontal.png';
window.__rasayaAssets = { appIconUrl, logoHorizontalUrl };