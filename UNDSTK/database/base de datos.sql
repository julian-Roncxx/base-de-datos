use UnderStock;

create table formregistro (
nombre varchar(100) not null,
correoRegistro varchar(100) not null unique,
claveRegistro varchar(255) not null
);

select * from formregistro ;