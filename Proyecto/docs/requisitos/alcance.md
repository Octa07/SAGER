# Alcance del proyecto

**Sistema:** SAGER  
**Versión:** 1.0

## Funciones incluidas

SAGER permite reservar servicios indicando una fecha y una hora. Antes de guardar la cita, el sistema comprueba que el horario no esté ocupado y que haya suficientes insumos asignados al servicio. Al confirmar la reserva, registra el consumo de esa cita y descuenta las unidades correspondientes del stock general y del stock del servicio.

Desde el panel administrativo se pueden consultar las citas, ajustar por separado el consumo de una cita, configurar cuántas unidades requiere cada servicio y registrar compras de insumos. Las compras aumentan el stock general y el saldo del servicio seleccionado.

## Usuarios

- **Cliente:** elige un servicio, indica sus datos y solicita una fecha y una hora. El sistema muestra si la reserva se confirmó o si debe elegir otro horario.
- **Administrador:** revisa las citas y existencias, configura los insumos por servicio y registra los cambios de consumo o las compras.

## Fuera del alcance

Esta versión no incluye pagos en línea ni administración de otras áreas del negocio. La reserva se limita a verificar y registrar el horario solicitado; no muestra un calendario de disponibilidad en tiempo real.

## Implementación

La aplicación está desarrollada en PHP y utiliza MySQL para guardar los servicios, las citas y los movimientos de stock. La reserva y el descuento de insumos se realizan dentro de una transacción para evitar que la cita quede registrada sin actualizar las existencias.