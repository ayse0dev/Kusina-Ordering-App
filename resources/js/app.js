import Alpine from 'alpinejs';
import './echo';
import registerKusina from './kusina';
import initMotion from './motion';

window.Alpine = Alpine;

registerKusina(Alpine);

Alpine.start();

initMotion();
