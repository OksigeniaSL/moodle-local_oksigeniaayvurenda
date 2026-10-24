<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Spanish strings for local_oksigeniaayvurenda.
 *
 * @package    local_oksigeniaayvurenda
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['audience'] = 'Para';
$string['backto'] = 'Volver a {$a}';
$string['backtolibrary'] = 'Volver a la biblioteca';
$string['books'] = '{$a} libros';
$string['cachedef_vocabulary'] = 'Palabras de cada catálogo, para sugerir búsquedas';
$string['callnumber'] = 'Tejuelo';
$string['chooselibrary'] = 'Elige una biblioteca';
$string['collection'] = 'Colección';
$string['copies'] = 'Ejemplares';
$string['corners'] = 'Rincones';
$string['deskbadletter'] = 'No se ha leído bien. Pásalo otra vez.';
$string['deskcatalogue'] = 'Catálogo';
$string['deskchoose'] = 'Hay varios lectores con «{$a}». ¿Quién es?';
$string['deskchoosehint'] = 'Elige al lector:';
$string['deskclose'] = 'Cerrar';
$string['deskdismiss'] = 'Dejarlo';
$string['deskerror'] = 'No ha llegado al servidor. Vuelve a pasarlo.';
$string['desklate'] = 'Tiene libros con retraso.';
$string['desklatedays'] = '{$a} días de retraso';
$string['desklateoneday'] = '1 día de retraso';
$string['desklendanyway'] = 'Prestar igualmente';
$string['desklent'] = 'Prestado: «{$a->title}». Vuelve el {$a->due}.';
$string['deskloans'] = 'Tiene prestados:';
$string['deskneedreader'] = 'Pasa primero el carné de quien se lo lleva: «{$a}».';
$string['desknoloans'] = 'No tiene libros prestados.';
$string['desknorenew'] = 'Ya no se puede ampliar más: «{$a}».';
$string['deskopen'] = 'Abrir el mostrador';
$string['deskprompt'] = 'Pasa el carné o el libro';
$string['deskreaderopen'] = 'Lector en el mostrador';
$string['deskrenew'] = 'Ampliar';
$string['deskrenewed'] = 'Ampliado: «{$a->title}», hasta el {$a->due}.';
$string['deskreturned'] = 'Devuelto: «{$a->title}», de {$a->name}.';
$string['deskreturnedlate'] = 'Devuelto con retraso: «{$a->title}», de {$a->name}.';
$string['deskreturnednoloan'] = 'Devuelto: «{$a}».';
$string['deskstoplate'] = '«{$a->title}»: tiene un libro con retraso.';
$string['deskstoplimit'] = '«{$a->title}»: ya tiene todos los libros que le tocan.';
$string['deskstopnotlendable'] = '«{$a->title}» no se presta: es para leer aquí.';
$string['deskstopunavailable'] = '«{$a->title}» no está disponible: perdido, retirado o en arreglo.';
$string['desktitle'] = 'Mostrador';
$string['deskundo'] = 'Deshacer';
$string['deskundone'] = 'Deshecho: «{$a}».';
$string['deskunknown'] = 'No conozco «{$a}». Busca el título en el catálogo o vuelve a pasarlo.';
$string['didyoumean'] = '¿Querías decir…?';
$string['emptycatalogue'] = 'Esta biblioteca todavía no tiene libros.';
$string['extent'] = 'Extensión';
$string['here'] = 'Aquí';
$string['homeheading'] = 'La biblioteca';
$string['isbn'] = 'ISBN';
$string['language'] = 'Idioma';
$string['local/oksigeniaayvurenda:catalogue'] = 'Catalogar la biblioteca';
$string['local/oksigeniaayvurenda:desk'] = 'Prestar y recoger en el mostrador';
$string['local/oksigeniaayvurenda:manage'] = 'Configurar las bibliotecas';
$string['local/oksigeniaayvurenda:view'] = 'Consultar la biblioteca';
$string['more'] = 'Más libros';
$string['navlibrary'] = 'Biblioteca';
$string['nolibrary'] = 'Todavía no hay ninguna biblioteca.';
$string['noresults'] = 'No hay nada con «{$a}».';
$string['noteloved'] = 'Le encantó';
$string['notemore'] = 'Quiere más así';
$string['noteunfinished'] = 'No lo terminó';
$string['onebook'] = '1 libro';
$string['oneresult'] = '1 libro con «{$a->query}»';
$string['onlyfree'] = 'Solo los libres';
$string['pluginname'] = 'Oksigenia Ayvurenda';
$string['privacy:metadata:loan'] = 'Los préstamos de cada lector, con el grupo en el que estaba en ese momento.';
$string['privacy:metadata:loan:groupname'] = 'El grupo del lector cuando se hizo el préstamo.';
$string['privacy:metadata:loan:itemid'] = 'El ejemplar o el material prestado.';
$string['privacy:metadata:loan:readerid'] = 'El lector que se lo llevó.';
$string['privacy:metadata:loan:renewals'] = 'Cuántas veces se amplió el préstamo.';
$string['privacy:metadata:loan:returnnote'] = 'Lo que dijo el lector del libro al devolverlo, apuntado con un toque.';
$string['privacy:metadata:loan:returnstaffid'] = 'Quién del personal lo recogió.';
$string['privacy:metadata:loan:staffid'] = 'Quién del personal lo prestó.';
$string['privacy:metadata:loan:timedue'] = 'Cuándo hay que devolverlo.';
$string['privacy:metadata:loan:timeloaned'] = 'Cuándo se prestó.';
$string['privacy:metadata:loan:timereturned'] = 'Cuándo se devolvió.';
$string['privacy:metadata:member'] = 'El grupo y la etapa de cada lector en cada curso escolar.';
$string['privacy:metadata:member:groupname'] = 'El grupo del lector ese curso.';
$string['privacy:metadata:member:readerid'] = 'El lector.';
$string['privacy:metadata:member:stage'] = 'La etapa del lector ese curso.';
$string['privacy:metadata:reader'] = 'Los lectores de cada biblioteca, enlazados con su cuenta cuando la tienen.';
$string['privacy:metadata:reader:cardcode'] = 'El código del carné del lector.';
$string['privacy:metadata:reader:email'] = 'El correo de un lector sin cuenta.';
$string['privacy:metadata:reader:firstname'] = 'El nombre de un lector sin cuenta.';
$string['privacy:metadata:reader:lastname'] = 'Los apellidos de un lector sin cuenta.';
$string['privacy:metadata:reader:notes'] = 'Notas del personal de la biblioteca sobre el lector.';
$string['privacy:metadata:reader:readertype'] = 'El tipo de lector: alumno, profesor, personal, familia…';
$string['privacy:metadata:reader:status'] = 'Si el lector está activo, suspendido o se ha ido.';
$string['privacy:metadata:reader:userid'] = 'La cuenta del lector.';
$string['privacy:path:desk'] = 'Préstamos atendidos en el mostrador';
$string['privacy:path:reader'] = 'Lector {$a}';
$string['publisher'] = 'Editorial';
$string['recent'] = 'Acaban de llegar';
$string['recordnotfound'] = 'Este libro no está en el catálogo.';
$string['results'] = '{$a->count} libros con «{$a->query}»';
$string['roleadapter'] = 'adaptación';
$string['roleeditor'] = 'edición';
$string['roleillustrator'] = 'ilustraciones';
$string['rolelibrarian'] = 'Bibliotecario (Ayvurenda)';
$string['rolelibrarian_desc'] = 'Lleva la biblioteca: la consulta, presta y recoge en el mostrador y cataloga. Se asigna en el sitio.';
$string['roleprefacer'] = 'prólogo';
$string['roletranslator'] = 'traducción';
$string['searchbutton'] = 'Buscar';
$string['searchlabel'] = 'Busca en la biblioteca';
$string['searchplaceholder'] = 'Título, autor o tema';
$string['skip'] = 'Ir al contenido';
$string['stateconsult'] = 'Para leer aquí';
$string['stateelsewhere'] = 'En otra biblioteca';
$string['statefree'] = 'Libre';
$string['statenone'] = 'Sin ejemplares';
$string['stateout'] = 'Prestado';
$string['subjects'] = 'Temas';
$string['summary'] = 'De qué va';
$string['todaylate'] = 'Con retraso';
$string['todayout'] = 'Prestados ahora';
$string['todaytoday'] = 'Vuelven hoy';
$string['tryother'] = 'Prueba con otra palabra o pregunta en el mostrador.';
$string['udc0'] = 'Generalidades';
$string['udc1'] = 'Filosofía y psicología';
$string['udc2'] = 'Religión';
$string['udc3'] = 'Ciencias sociales';
$string['udc5'] = 'Ciencias naturales';
$string['udc6'] = 'Ciencias aplicadas';
$string['udc7'] = 'Arte y deporte';
$string['udc8'] = 'Lengua y literatura';
$string['udc9'] = 'Geografía e historia';
$string['year'] = 'Año';
