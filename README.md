# PowerPress Podcast Stats

Un plugin de WordPress para rastrear estadísticas de acceso a feeds RSS de podcasts configurados con Blubrry PowerPress.

## Características

- 📊 Estadísticas en tiempo real de accesos a feeds de podcast
- 🔍 **Detección automática de feeds de PowerPress**
- ✏️ **Registro manual de feeds**
- 🎙️ **Soporte para múltiples podcasts** (diferenciados y organizados)
- 🌍 Geolocalización por país y ciudad (usando ip-api.com)
- 📱 Detección de apps de podcast y navegadores
- 📅 Filtros temporales: semana, mes, año, todo el tiempo, y rango personalizado
- 🔒 Privacidad: las IPs se hashean, solo se guarda ubicación
- 📈 Gráficas visuales de accesos por feed, país, ciudad y timeline
- ♾️ Almacenamiento permanente de datos históricos

## Requisitos

- WordPress 5.0 o superior
- PHP 7.2 o superior
- Plugin Blubrry PowerPress instalado y configurado

## Instalación

1. **Sube el plugin a WordPress:**
   - Copia la carpeta `powerpress-podcast-stats` a `/wp-content/plugins/`
   - O sube el archivo ZIP desde el panel de WordPress

2. **Activa el plugin:**
   - Ve a Plugins → Plugins instalados
   - Busca "PowerPress Podcast Stats"
   - Haz clic en "Activar"

3. **Accede a las estadísticas:**
   - En el menú lateral de WordPress verás "Podcast Stats"
   - Haz clic para ver el panel de estadísticas

## Uso

### Primer Uso - Registro de Feeds

Cuando actives el plugin por primera vez, verás una pantalla para registrar tus feeds:

1. **Detección automática:**
   - Haz clic en "Detect PowerPress Feeds"
   - El plugin escaneará tu instalación de PowerPress
   - Mostrará todos los feeds encontrados (principal, por categorías, taxonomías, tipos de post)
   - Haz clic en "Register" junto a cada feed que quieras rastrear

2. **Registro manual:**
   - Haz clic en "Add Feed Manually"
   - Introduce la URL completa del feed (ej: `https://tusitio.com/feed/podcast/`)
   - Dale un nombre descriptivo al podcast
   - Haz clic en "Save Feed"

3. **Múltiples podcasts:**
   - Puedes tener varios podcasts diferentes
   - Cada podcast puede tener múltiples feeds (categorías, etc.)
   - El plugin los organizará por nombre de podcast

### Panel de Estadísticas

El panel muestra:

1. **Total de accesos a feeds** - Número total de peticiones registradas
2. **Accesos por feed** - Desglose de cada feed de podcast
3. **Accesos por país** - Top 20 países desde donde se accede
4. **Accesos por ciudad** - Top 20 ciudades con más accesos
5. **Apps de podcast** - Top 10 clientes/apps que acceden a tus feeds
6. **Timeline** - Gráfica de los últimos 30 días

### Filtros Disponibles

- **Podcast:** Filtra por podcast específico o muestra todos
- **Período de tiempo:**
  - Última semana
  - Último mes (predeterminado)
  - Último año
  - Todo el tiempo
  - Rango personalizado (selecciona fechas de inicio y fin)

### Gestión de Feeds

En la sección "Managed Podcast Feeds" puedes:
- Detectar nuevos feeds de PowerPress
- Añadir feeds manualmente
- Ver todos los feeds registrados

## Cómo Funciona

### Recolección de Datos

1. El plugin intercepta todas las peticiones a feeds RSS
2. Identifica si es un feed de podcast (creado por PowerPress)
3. Extrae información:
   - Slug del feed
   - User-Agent (app/navegador usado)
   - IP del visitante
4. Hash de la IP para privacidad
5. Consulta geolocalización en ip-api.com
6. Guarda el registro en la base de datos

### Prevención de Duplicados

- Cada IP única solo se cuenta **una vez por hora** por feed
- Esto evita inflar las estadísticas con recargas múltiples
- Las apps de podcast que hacen polling frecuente no se cuentan cada vez

### Geolocalización

El plugin usa **ip-api.com**, un servicio gratuito que permite:
- 45 peticiones por minuto
- Sin necesidad de API key
- Datos de país y ciudad
- Los datos se cachean durante 7 días para evitar llamadas repetidas

### Privacidad

- Las IPs **nunca se muestran** en la interfaz
- Se genera un hash SHA-256 de la IP + salt de WordPress
- Solo se almacenan país y ciudad
- Los datos de geolocalización se cachean para reducir llamadas externas

## Estructura de la Base de Datos

### Tabla de estadísticas: `wp_powerpress_feed_stats`

```sql
CREATE TABLE wp_powerpress_feed_stats (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    feed_slug varchar(255) NOT NULL,
    feed_name varchar(255) NOT NULL,
    podcast_id bigint(20) DEFAULT 0,
    user_agent text,
    ip_hash varchar(64) NOT NULL,
    country varchar(100) DEFAULT '',
    city varchar(100) DEFAULT '',
    access_time datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY feed_slug (feed_slug),
    KEY podcast_id (podcast_id),
    KEY access_time (access_time),
    KEY ip_hash (ip_hash),
    KEY country (country)
);
```

### Tabla de feeds registrados: `wp_powerpress_registered_feeds`

```sql
CREATE TABLE wp_powerpress_registered_feeds (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    podcast_name varchar(255) NOT NULL,
    feed_url varchar(500) NOT NULL,
    feed_slug varchar(255) NOT NULL,
    source varchar(50) DEFAULT 'manual',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY feed_slug (feed_slug),
    KEY podcast_name (podcast_name)
);
```

## Limitaciones Conocidas

1. **No hay datos históricos** - El plugin solo registra accesos desde su activación
2. **No rastrea reproducciones** - Solo cuenta cuándo las apps consultan el feed RSS
3. **Apps que cachean** - Algunas apps descargan el feed raramente, así que los datos no reflejan escuchas exactas
4. **Límite de API** - ip-api.com tiene un límite de 45 req/min (más que suficiente para la mayoría de sitios)
5. **Geolocalización aproximada** - Los datos de ciudad pueden no ser exactos

## Notas sobre PowerPress

El plugin detecta automáticamente feeds de PowerPress:
- Feed principal de podcast
- Feeds por categoría
- Feeds por tipo de publicación
- Feeds personalizados

Si PowerPress crea feeds con slugs personalizados, el plugin los detectará automáticamente.

## Solución de Problemas

### No se registran accesos

1. Verifica que PowerPress esté activo
2. Comprueba que tienes episodios publicados
3. Prueba a acceder manualmente al feed: `tusitio.com/feed/podcast/`
4. Revisa los errores de PHP en el log

### Los datos de ubicación están vacíos

1. Verifica que tu servidor pueda hacer peticiones HTTP externas
2. Comprueba que ip-api.com esté accesible desde tu servidor
3. Si usas CloudFlare o proxy, verifica que `HTTP_CF_CONNECTING_IP` esté disponible

### El panel no carga

1. Abre la consola del navegador (F12)
2. Busca errores JavaScript
3. Verifica que el AJAX esté funcionando
4. Comprueba los permisos de usuario (necesitas rol de administrador)

## Mejoras Futuras

Posibles características a añadir:
- Exportación de datos a CSV/Excel
- Gráficas más avanzadas
- Notificaciones de hitos (ej: 1000 accesos)
- Comparación de períodos
- Filtro por episodio específico
- Integración con Google Analytics
- Soporte para otros servicios de geolocalización

## Changelog

### 1.1.0 (2024-12-07)
- **Nuevo:** Detección automática de feeds de PowerPress
- **Nuevo:** Registro manual de feeds con interfaz amigable
- **Nuevo:** Soporte para múltiples podcasts diferenciados
- **Nuevo:** Tabla de feeds registrados en base de datos
- **Mejorado:** Organización de estadísticas por podcast
- **Mejorado:** Auto-registro de feeds cuando se acceden
- **Mejorado:** Interfaz de usuario con botones de gestión

### 1.0.0 (2024-12-07)
- Lanzamiento inicial
- Tracking de feeds de PowerPress
- Geolocalización con ip-api.com
- Panel de estadísticas con filtros
- Gráficas de accesos por feed, país, ciudad, app y timeline
- Hash de IPs para privacidad

## Créditos

- Desarrollado por Flavia
- Geolocalización proporcionada por [ip-api.com](https://ip-api.com/)
- Diseñado para trabajar con [Blubrry PowerPress](https://wordpress.org/plugins/powerpress/)

## Licencia

GPL v2 o superior

## Soporte

Para reportar bugs o solicitar características, contacta al desarrollador.
# powerpress-podcast-stats
