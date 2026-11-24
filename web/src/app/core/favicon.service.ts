import { DOCUMENT } from '@angular/common';
import { effect, inject, Injectable } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router } from '@angular/router';
import { filter, map } from 'rxjs';
import { AuthService } from './auth.service';

/** Ícone da ABianchi (o mesmo do site abianchitech.com). */
const FAVICON_ABIANCHI = 'favicon-abianchi.svg';

/**
 * Ícone da aba do navegador: na área de administração, o da ABianchi; no painel da barbearia
 * (e no do barbeiro), o mesmo da página da barbearia (logo ou inicial na cor dela).
 */
@Injectable({ providedIn: 'root' })
export class FaviconService {
  private readonly documento = inject(DOCUMENT);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly url = toSignal(
    this.router.events.pipe(
      filter((e): e is NavigationEnd => e instanceof NavigationEnd),
      map((e) => e.urlAfterRedirects),
    ),
    { initialValue: this.router.url },
  );

  constructor() {
    effect(() => {
      const admin = this.url().startsWith('/admin');
      this.aplicar((!admin && this.auth.sessao()?.empresa?.favicon_url) || FAVICON_ABIANCHI);
    });
  }

  private aplicar(href: string): void {
    let link = this.documento.querySelector<HTMLLinkElement>('link[rel="icon"]');
    if (!link) {
      link = this.documento.createElement('link');
      link.rel = 'icon';
      this.documento.head.appendChild(link);
    }
    if (link.getAttribute('href') === href) return;
    link.type = href.endsWith('.svg') || href.startsWith('data:image/svg') ? 'image/svg+xml' : '';
    link.href = href;
  }
}
