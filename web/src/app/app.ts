import { Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { FaviconService } from './core/favicon.service';

@Component({
  imports: [RouterOutlet],
  selector: 'app-root',
  template: '<router-outlet />',
})
export class App {
  // Troca o ícone da aba conforme a área: administração (ABianchi) ou barbearia.
  private readonly favicon = inject(FaviconService);
}
