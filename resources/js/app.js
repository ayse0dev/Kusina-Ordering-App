import Alpine from 'alpinejs';
import './echo';
import registerKusina from './kusina';

window.Alpine = Alpine;

registerKusina(Alpine);

Alpine.start();
