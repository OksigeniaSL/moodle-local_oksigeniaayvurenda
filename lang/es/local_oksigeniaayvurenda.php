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
$string['cardcode'] = 'Carné';
$string['chooselibrary'] = 'Elige una biblioteca';
$string['collection'] = 'Colección';
$string['copies'] = 'Ejemplares';
$string['copiesempty'] = 'Todavía no hay copias: la primera se hace esta noche.';
$string['copiesheading'] = 'Copias de la biblioteca';
$string['copiesintro'] = 'Cada noche, una copia completa del catálogo, los ejemplares, los lectores y los préstamos, en ficheros abiertos (CSV en un zip) que abre cualquier hoja de cálculo. Aquí se guardan las {$a} últimas. Descarga una de vez en cuando y guárdala fuera del sitio: si el sitio se cae algún día, la biblioteca no se queda sin sus datos.';
$string['copiesnav'] = 'Copias';
$string['copydownload'] = 'Descargar';
$string['copymade'] = 'Copia hecha.';
$string['copymake'] = 'Hacer una copia ahora';
$string['copyreadme'] = "Copia de las bibliotecas de {\$a->entity}, hecha el {\$a->date} por Oksigenia Ayvurenda {\$a->release}.\n\nCada fichero CSV es una tabla, con los nombres de las columnas en la primera fila. Se abren con cualquier hoja de cálculo.\n\n- entity, library: la entidad y sus bibliotecas.\n- record, authority, recauth: el catálogo (títulos, autores, materias…).\n- item: los ejemplares y el material, con sus códigos.\n- reader, member: los lectores y su grupo de cada curso.\n- loan: los préstamos, en curso y devueltos.\n- rule, year, tutor, source: normas de préstamo, cursos, tutores y de dónde salió cada fila.\n\nFilas de cada fichero:\n{\$a->counts}\n";
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
$string['lateall'] = 'Ver todos los prestados';
$string['lateallempty'] = 'No hay libros prestados.';
$string['lateallheading'] = 'Libros prestados';
$string['lateempty'] = 'No hay libros con retraso.';
$string['lateheading'] = 'Libros con retraso';
$string['lateonly'] = 'Ver solo los de retraso';
$string['lateuntil'] = 'hasta el {$a}';
$string['local/oksigeniaayvurenda:catalogue'] = 'Catalogar la biblioteca';
$string['local/oksigeniaayvurenda:desk'] = 'Prestar y recoger en el mostrador';
$string['local/oksigeniaayvurenda:manage'] = 'Configurar las bibliotecas';
$string['local/oksigeniaayvurenda:view'] = 'Consultar la biblioteca';
$string['matchdone'] = 'Hecho: {$a}.';
$string['matchdoubt'] = 'Dudosos: {$a}';
$string['matchdoubtbutton'] = 'Guardar los elegidos';
$string['matchempty'] = 'No hay lectores sin cuenta.';
$string['matchheading'] = 'Casar lectores con sus cuentas';
$string['matchintro'] = 'Los lectores que llegaron sin cuenta (de Abies o de una lista), contra las cuentas del sitio. Un nombre solo nunca enlaza a nadie sin que lo veas: los que casan solos se aceptan de una vez; los dudosos, uno a uno, y los que no están siguen siendo lectores sin cuenta, con su historial.';
$string['matchlink'] = 'Casar lectores sin cuenta ({$a})';
$string['matchmore'] = 'Y {$a} más.';
$string['matchnobody'] = 'Ninguno de estos';
$string['matchnone'] = 'No están en el sitio: {$a}';
$string['matchnonebutton'] = 'Marcarlos como antiguos lectores';
$string['matchnoneintro'] = 'Siguen siendo lectores sin cuenta y pueden llevarse libros con su carné. Si ya no están en el centro, márcalos como antiguos lectores: conservan su historial.';
$string['matchsure'] = 'Casan solos: {$a}';
$string['matchsurebutton'] = 'Enlazar los {$a}';
$string['members'] = '{$a} miembros';
$string['messageprovider:overdue'] = 'Un libro de la biblioteca que devolver';
$string['messageprovider:overduetutor'] = 'Libros de la biblioteca con retraso en mi grupo';
$string['more'] = 'Más libros';
$string['navlibrary'] = 'Biblioteca';
$string['nolibrary'] = 'Todavía no hay ninguna biblioteca.';
$string['noresults'] = 'No hay nada con «{$a}».';
$string['noteloved'] = 'Le encantó';
$string['notemore'] = 'Quiere más así';
$string['noteunfinished'] = 'No lo terminó';
$string['noticereaderbody'] = "Hola, {\$a->name}:\n\nEn la biblioteca ({\$a->library}) te esperan:\n{\$a->books}\n\nSi no lo has terminado, pide en el mostrador que te lo amplíen. ¡Gracias!";
$string['noticereaderline'] = '- «{$a->title}», que tocaba el {$a->due}';
$string['noticereadersubject'] = 'Un libro que devolver a la biblioteca';
$string['noticereadersubjectmany'] = 'Libros que devolver a la biblioteca';
$string['noticetutorbody'] = "Hola, {\$a->name}:\n\nEn {\$a->group}, estos lectores tienen libros de la biblioteca ({\$a->library}) con retraso:\n{\$a->readers}\n\nUn recordatorio en clase ayuda. Te llega una vez por semana mientras haya libros con retraso.";
$string['noticetutorline'] = '- {$a->name}: {$a->books} libros, hasta {$a->days} días de retraso';
$string['noticetutorlineone'] = '- {$a->name}: 1 libro, {$a->days} días de retraso';
$string['noticetutorsubject'] = 'Libros de la biblioteca con retraso en {$a->group}';
$string['onebook'] = '1 libro';
$string['onemember'] = '1 miembro';
$string['onereader'] = '1 lector';
$string['oneresult'] = '1 libro con «{$a->query}»';
$string['onlyfree'] = 'Solo los libres';
$string['pluginname'] = 'Oksigenia Ayvurenda';
$string['privacy:metadata:core_files'] = 'Las copias diarias de la biblioteca, que llevan sus lectores y préstamos, se guardan como ficheros siete días.';
$string['privacy:metadata:core_message'] = 'A los lectores se les avisa de los libros con retraso, y a los tutores de los de su grupo, con mensajes de Moodle.';
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
$string['privacy:metadata:loan:timenotified'] = 'Cuándo se avisó por última vez al lector del retraso.';
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
$string['privacy:metadata:tutor'] = 'El tutor de cada grupo en cada curso, al que se avisa de los libros con retraso.';
$string['privacy:metadata:tutor:groupname'] = 'El grupo.';
$string['privacy:metadata:tutor:timenotified'] = 'Cuándo se avisó por última vez al tutor de los retrasos.';
$string['privacy:metadata:tutor:userid'] = 'El tutor.';
$string['privacy:path:desk'] = 'Préstamos atendidos en el mostrador';
$string['privacy:path:reader'] = 'Lector {$a}';
$string['privacy:path:tutor'] = 'Tutor';
$string['publisher'] = 'Editorial';
$string['readers'] = 'Lectores';
$string['readersback'] = 'Todos los grupos';
$string['readerscount'] = '{$a} lectores';
$string['readersempty'] = 'Todavía no hay grupos de este curso. Elige de qué cohortes salen los lectores.';
$string['readersnogroup'] = 'Sin grupo';
$string['readersnone'] = 'No hay lectores aquí.';
$string['readerssources'] = 'De dónde salen los lectores';
$string['readertypefamily'] = 'Familias';
$string['readertypestaff'] = 'Personal';
$string['readertypestudent'] = 'Alumnos';
$string['readertypeteacher'] = 'Profesores';
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
$string['sourceschosen'] = 'Lectores';
$string['sourcesgroup'] = 'Su nombre es el grupo';
$string['sourcesintro'] = 'Marca las cohortes del sitio cuyos miembros son lectores de esta biblioteca y di de qué tipo son. Si el nombre de la cohorte es el grupo (por ejemplo, «3.º Primaria A»), la biblioteca guarda en qué grupo estuvo cada lector cada curso. Llegan solos cada noche; también puedes traerlos ahora.';
$string['sourcesnocohorts'] = 'El sitio todavía no tiene cohortes.';
$string['sourcessave'] = 'Guardar';
$string['sourcessaved'] = 'Guardado.';
$string['sourcesstage'] = 'Etapa';
$string['sourcessynced'] = 'Hecho: {$a->created} lectores nuevos, {$a->updated} cambiados, {$a->back} que vuelven y {$a->left} que se han ido. En total, {$a->total}.';
$string['sourcessyncnow'] = 'Guardar y traer los lectores ahora';
$string['sourcestype'] = 'Tipo';
$string['stateconsult'] = 'Para leer aquí';
$string['stateelsewhere'] = 'En otra biblioteca';
$string['statefree'] = 'Libre';
$string['statenone'] = 'Sin ejemplares';
$string['stateout'] = 'Prestado';
$string['subjects'] = 'Temas';
$string['summary'] = 'De qué va';
$string['taskmakecopies'] = 'Hacer la copia diaria de las bibliotecas';
$string['tasksendnotices'] = 'Avisar a lectores y tutores de los libros con retraso';
$string['tasksyncreaders'] = 'Traer los lectores de las cohortes';
$string['todaylate'] = 'Con retraso';
$string['todayout'] = 'Prestados ahora';
$string['todaytoday'] = 'Vuelven hoy';
$string['tryother'] = 'Prueba con otra palabra o pregunta en el mostrador.';
$string['tutornocandidates'] = 'Todavía no hay profesores ni personal con cuenta entre los lectores. Elige de dónde salen en «De dónde salen los lectores».';
$string['tutornone'] = 'Sin tutor';
$string['tutorproposed'] = 'Propuesto: el del curso pasado';
$string['tutorsheading'] = 'Tutores de {$a}';
$string['tutorsintro'] = 'El tutor de cada grupo recibe, una vez por semana, quién del grupo tiene libros con retraso y desde cuándo, sin los títulos. Los lectores con cuenta reciben su propio aviso, amable, el primer día y luego una vez por semana.';
$string['tutorsnav'] = 'Tutores';
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
