FROM php:8.2-apache

# Habilitar mod_rewrite do Apache
RUN a2enmod rewrite

# Instalar extensões do PHP necessárias (ex: pdo_mysql, mysqli)
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Configurar ServerName para evitar avisos no apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Alterar a porta do Apache para a porta fornecida pelo Render (variável PORT)
# O Render dinamicamente injeta a variável PORT, então precisamos que o Apache escute nela
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf

# Copiar os arquivos do projeto para o diretório root do Apache
COPY . /var/www/html/

# Configurar permissões adequadas
RUN chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/

# O Render usará o comando padrão de inicialização do Apache da imagem base
