# SAGER — Sistema de Agendamiento y Control de Reservas

## Descripción

SAGER permite reservar servicios desde un formulario público y controlar los insumos utilizados. El panel administrativo muestra las citas y el stock asignado a cada servicio.

## Funcionalidades

### Citas
- El cliente elige un servicio, una fecha y una hora.
- El sistema verifica que el horario no esté ocupado y confirma la reserva si hay stock suficiente.

### Insumos y stock
- Cada servicio tiene una configuración de insumos y una cantidad de consumo por cita.
- Cada cita conserva su propio consumo. Editar una cita ajusta el stock solo por la diferencia registrada en esa cita.
- Las compras aumentan el stock general y el saldo del servicio elegido; las reservas descuentan ambos.
- El panel muestra alertas cuando el stock general llega al mínimo configurado.

### Panel administrativo
- Permite consultar las citas, editar el consumo de cada cita y administrar servicios, insumos y stock.
- El acceso requiere una cuenta con rol de administrador.

## Estructura del proyecto

| Directorio o archivo | Descripción |
|---|---|
| `admin/` | Entradas del panel administrativo y del inicio de sesión. |
| `config/` | Conexión a MySQL y funciones de sesión y seguridad. |
| `controllers/` | Controladores de reservas, autenticación, panel y acciones administrativas. |
| `database/` | Esquema, datos iniciales y migraciones de la base de datos. |
| `docs/` | Documento con el alcance del proyecto. |
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
- `insumos`: registra los artículos, el stock general y el mínimo de referencia.
- `servicio_insumos`: indica qué insumos necesita cada servicio y en qué cantidad.
- `servicio_insumo_stock`: mantiene el stock asignado a cada servicio. El stock general se guarda en `insumos`.
- `citas`: almacena las reservas realizadas.
- `cita_insumos`: conserva las cantidades de insumos descontadas por cada cita.

### Inicialización

Los archivos `Proyecto/database/schema.sql` y `Proyecto/database/datos_iniciales.sql` contienen la definición del esquema y los datos iniciales para preparar el entorno.

- Las tablas usan InnoDB para permitir transacciones y relaciones mediante llaves foráneas.
- El archivo de datos iniciales crea servicios e insumos para preparar la base.
- Al registrar una compra desde el panel, las unidades se suman al stock general y al saldo del servicio elegido.

### Actualización de una instalación existente

Ejecuta `Proyecto/database/migrations/ACT.sql` en la base `sistema_reservas`. Esta migración crea las relaciones de consumo por servicio y por cita, agrega los saldos de stock por servicio y conserva el consumo anterior asociándolo al primer insumo disponible.

Como el stock previo no estaba separado por servicio, `ACT.sql` lo distribuye entre los servicios que usan cada insumo en proporción a su consumo configurado. El total se conserva y cualquier sobrante por redondeo se asigna al servicio con el ID menor. Las nuevas compras se asignan al servicio elegido en el panel.

Cada cita guarda su propio consumo. Al editarlo, el stock general y el saldo de ese servicio cambian solo por la diferencia de la cita. La configuración de consumo por servicio se aplica a las nuevas reservas.

### Credenciales de conexión local

| Parámetro | Valor |
|---|---|
| Base de datos | `sistema_reservas` |
| Usuario | `usuarioClases` |
| Contraseña | `Pass123` |

Estas credenciales corresponden al entorno local. Deben sustituirse por credenciales seguras antes de utilizar el sistema en un entorno real.

## Ejecución del sistema

1. Configurar el entorno local con un servidor web compatible con PHP y MySQL.
2. Crear e inicializar la base de datos utilizando `Proyecto/database/schema.sql` y `Proyecto/database/datos_iniciales.sql`.
3. Colocar el proyecto en el directorio público del servidor web.
4. Verificar que los parámetros de conexión de la aplicación coincidan con la configuración de MySQL.
5. Iniciar el servidor web y el servicio de base de datos.

### Acceso a la interfaz pública

Con el proyecto instalado en la carpeta `sager` del servidor local, abrir:

```text
http://localhost/sager/
```

Si se utiliza otra carpeta o configuración, ajustar la dirección a la ruta correspondiente.

### Acceso al panel administrativo

Con el proyecto instalado en la carpeta `sager` del servidor local, abrir:

```text
http://localhost/sager/admin/login.php
```

### Crear una cuenta administradora

Ejecuta el script desde una terminal en la carpeta `Proyecto`. El script solicitará el nombre, el correo y una contraseña para la cuenta administradora; esta última debe tener al menos 12 caracteres.

```powershell
Set-Location .\SAGER-main\Proyecto

$env:DB_HOST = "localhost"
$env:DB_NAME = "sistema_reservas"
$env:DB_USER = "usuarioClases"
$env:DB_PASSWORD = "Pass123"

php .\crear_admin.php
```
