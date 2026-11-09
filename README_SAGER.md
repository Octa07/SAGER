# SAGER — Sistema de Agendamiento y Control de Reservas

## Descripción

SAGER es una aplicación web transaccional diseñada para gestionar reservas de servicios y mantener sincronizado el agendamiento de citas con el inventario físico del negocio.

El sistema permite que los clientes registren sus citas desde una interfaz pública. A su vez, proporciona un módulo administrativo para consultar las reservas programadas y supervisar las existencias disponibles.

## Funcionalidades

### Agendamiento de citas
- Formulario público para registrar clientes.
- Selección del servicio, la fecha y la hora de la reserva.

### Disponibilidad y control de horarios
- Validación de disponibilidad antes de registrar una cita.
- Prevención del solapamiento de horarios mediante restricciones de unicidad en la base de datos.

### Gestión de inventario
- Descuento automático de los insumos asociados al servicio al confirmar una reserva.
- Sincronización entre la cita y el inventario mediante operaciones atómicas.
- Alertas visuales en el panel administrativo cuando las existencias alcanzan o quedan por debajo del mínimo establecido.

### Administración y seguridad
- Inicio de sesión para usuarios administradores.
- Validación de contraseñas mediante hash y manejo de sesiones.
- Panel privado para consultar las citas programadas y supervisar las existencias.

### Control de solicitudes
- Prevención de envíos múltiples del formulario mediante controles del lado del cliente con JavaScript.

## Estructura del proyecto

| Directorio o archivo | Descripción |
|---|---|
| `admin/` | Entradas del panel administrativo y del inicio de sesión. |
| `config/` | Conexión a MySQL y funciones de sesión y seguridad. |
| `controllers/` | Controladores de reservas, autenticación, panel y acciones administrativas. |
| `database/` | Esquema de la base de datos y datos de demostración. |
| `docs/` | Documentación del alcance, arquitectura, calidad y manuales. |
| `models/` | Consultas y operaciones relacionadas con usuarios, servicios, citas e inventario. |
| `public/` | Punto de entrada de la interfaz pública. |
| `tests/` | Scripts para validar las funciones de reservas y administración. |
| `views/` | Vistas de las interfaces pública y administrativa. |
| `crear_admin.php` | Script para crear una cuenta administradora desde la línea de comandos. |
| `index.php` | Redirección a la página pública. |
| `styles.css` | Estilos de la interfaz. |

## Base de datos

SAGER utiliza MySQL y una base de datos denominada `sistema_reservas`.

Las tablas principales son:

- `usuarios`: almacena las cuentas utilizadas para el acceso al sistema.
- `servicios`: contiene los servicios disponibles para reservar.
- `insumos`: registra los artículos y las existencias del inventario.
- `citas`: almacena las reservas realizadas.

### Inicialización

El archivo `database.sql` contiene la definición del esquema y los datos iniciales necesarios para preparar el entorno. Entre sus configuraciones se incluyen:

- Uso del motor **InnoDB** en las tablas, para permitir transacciones y establecer relaciones mediante llaves foráneas.
- Creación del usuario local `usuarioClases`, con permisos sobre el esquema `sistema_reservas`.
- Inserción de datos semilla para el funcionamiento inicial del sistema.

### Credenciales de conexión local

| Parámetro | Valor |
|---|---|
| Base de datos | `sistema_reservas` |
| Usuario | `usuarioClases` |
| Contraseña | `admin123` |

Estas credenciales corresponden al entorno local de demostración. Deben sustituirse por credenciales seguras antes de utilizar el sistema en un entorno real.

## Ejecución del sistema

1. Configurar el entorno local con un servidor web compatible con PHP y MySQL.
2. Crear e inicializar la base de datos utilizando el archivo `database.sql`.
3. Colocar el proyecto en el directorio público del servidor web.
4. Verificar que los parámetros de conexión de la aplicación coincidan con la configuración de MySQL.
5. Iniciar el servidor web y el servicio de base de datos.

### Acceso a la interfaz pública

Con el proyecto instalado en la carpeta `sager` del servidor local, abrir:

```text
http://localhost/sager/
```

Si se utiliza otra carpeta o configuración, ajustar la dirección a la ruta correspondiente.

### Acceso al módulo administrativo

Abrir la ruta de inicio de sesión configurada para el proyecto. La ruta indicada para el entorno descrito es:

```text
/views/admin/login.php
```

### Cuenta de demostración

El volcado de la base de datos incluye una cuenta administradora de prueba.

| Parámetro | Valor |
|---|---|
| Usuario | `admin@sager.com` |
| Contraseña | `admin123` |

La contraseña anterior corresponde al entorno de demostración. Se recomienda cambiarla antes de cualquier despliegue.

## Consideraciones

- La disponibilidad de las citas se protege tanto desde la aplicación como mediante restricciones en la base de datos.
- La actualización del inventario se realiza junto con el registro de la reserva mediante operaciones transaccionales, para mantener la consistencia de los datos.
- Las credenciales incluidas en esta documentación son únicamente para configuración y pruebas locales. No deben reutilizarse en producción.
