# Alcance del Proyecto (Versión 1.0)
**Proyecto:** Sistema de Agendamiento y Control de Reservas 

## 1. Análisis y Refinamiento de Requisitos Previos
Durante la revisión inicial del proyecto, se identificaron y corrigieron los siguientes problemas de diseño para esta primera versión:
*   **Requisitos ambiguos corregidos:** Se eliminó el concepto general de "gestionar el negocio". El enfoque se delimitó estrictamente a "gestión de citas y actualización automática de inventario" desde el propio sistema.
*   **Funcionalidades demasiado amplias ajustadas:** Se pospuso la integración de pasarelas de pago online para futuras versiones. La versión 1.0 se enfocará únicamente en asegurar la reserva y la integridad de los datos en la base de datos.
*   **Claridad en la interfaz:** Se determinó que el formulario de reserva debe prevenir errores de formato de fecha desde la vista (frontend) antes de enviar la petición al servidor, mejorando la usabilidad.

## 2. Definición del Problema
Actualmente, la gestión manual de citas genera fricción en la experiencia del cliente (tiempos de espera, confirmaciones lentas) y provoca desajustes operativos. Al no existir una conexión en tiempo real entre la agenda y el inventario físico, ocurren cruces de horarios y falta de insumos al momento de brindar el servicio.

## 3. Usuarios Identificados y Contexto de Uso
Para asegurar la calidad en uso del sistema, se definen los siguientes perfiles[cite: 2]:
*   **Cliente Final:** Usuario que requiere agendar un servicio de forma rápida, generalmente desde un dispositivo móvil. Su objetivo es asegurar un espacio sin fricciones y recibir retroalimentación inmediata (confirmación en pantalla) para tener certeza de su acción.
*   **Administrador / Personal:** Usuario que opera desde un panel web (Dashboard). Su objetivo es visualizar el calendario actualizado en tiempo real y controlar el stock de los insumos.

## 4. Alcance Final de la Versión 1.0
El sistema será una aplicación web funcional basada en una arquitectura cliente-servidor con PHP y MySQL. Permitirá a los clientes consultar disponibilidad en tiempo real y agendar citas. La lógica de negocio (Backend en PHP) procesará la confirmación de la cita y, en la misma transacción, actualizará el inventario correspondiente en la base de datos para garantizar la integridad de la información. El administrador contará con un panel de control unificado para visualizar las citas confirmadas y el estado actual de los insumos.