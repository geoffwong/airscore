FROM php:7.4.33-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    perl \
    libxml-simple-perl \
    default-mysql-client \
    vim \
    && rm -rf /var/lib/apt/lists/*

# Enable PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite
RUN a2enmod cgi

# Configure Apache for CGI
RUN echo "ScriptAlias /cgi-bin/ /usr/lib/cgi-bin/" >> /etc/apache2/apache2.conf
RUN echo "<Directory \"/usr/lib/cgi-bin\">" >> /etc/apache2/apache2.conf
RUN echo "    AllowOverride None" >> /etc/apache2/apache2.conf
RUN echo "    Options +ExecCGI -MultiViews +SymLinksIfOwnerMatch" >> /etc/apache2/apache2.conf
RUN echo "    Require all granted" >> /etc/apache2/apache2.conf
RUN echo "</Directory>" >> /etc/apache2/apache2.conf

# Create necessary directories
RUN mkdir -p /usr/lib/cgi-bin
RUN mkdir -p /var/airscore/tracks
RUN chmod 755 /var/airscore/tracks

# Set working directory
WORKDIR /var/www/html

# Copy Perl scripts to CGI directory first (before volume mount)
COPY *.pl /usr/lib/cgi-bin/
COPY *.pm /usr/lib/cgi-bin/
RUN chmod +x /usr/lib/cgi-bin/*.pl

# Create startup script
RUN echo '#!/bin/bash' > /start.sh && \
    echo 'echo "<?php" > /var/www/html/defines.php' >> /start.sh && \
    echo 'echo "define('"'"'BINDIR'"'"', '"'"'/usr/lib/cgi-bin'"'"');" >> /var/www/html/defines.php' >> /start.sh && \
    echo 'echo "define('"'"'FILEDIR'"'"', '"'"'/var/airscore/tracks/'"'"');" >> /var/www/html/defines.php' >> /start.sh && \
    echo 'echo "define('"'"'DATABASE'"'"', '"'"'xcdb'"'"');" >> /var/www/html/defines.php' >> /start.sh && \
    echo 'echo "define('"'"'MYSQLHOST'"'"', '"'"'db'"'"');" >> /var/www/html/defines.php' >> /start.sh && \
    echo 'echo "define('"'"'MYSQLUSER'"'"', '"'"'xc'"'"');" >> /var/www/html/defines.php' >> /start.sh && \
    echo 'echo "define('"'"'MYSQLPASSWORD'"'"', '"'"'airscore123'"'"');" >> /var/www/html/defines.php' >> /start.sh && \
    echo 'echo "?>" >> /var/www/html/defines.php' >> /start.sh && \
    echo '# Create MySQL constants for PHP 7.4 compatibility' >> /start.sh && \
    echo 'cat > /var/www/html/mysql_constants.php << "EOF"' >> /start.sh && \
    echo '<?php' >> /start.sh && \
    echo 'if (!defined("MYSQL_BOTH")) define("MYSQL_BOTH", 3);' >> /start.sh && \
    echo 'if (!defined("MYSQL_ASSOC")) define("MYSQL_ASSOC", 1);' >> /start.sh && \
    echo 'if (!defined("MYSQL_NUM")) define("MYSQL_NUM", 2);' >> /start.sh && \
    echo '?>' >> /start.sh && \
    echo 'EOF' >> /start.sh && \
    echo 'sed -i "1i<?php require_once('"'"'mysql_constants.php'"'"'); ?>" /var/www/html/oldmysql.php' >> /start.sh && \
    echo 'sed -i "s/function mysql_fetch_array(\$result, \$how)/function mysql_fetch_array(\$result, \$how = 3)/" /var/www/html/oldmysql.php' >> /start.sh && \
    echo 'apache2-foreground' >> /start.sh && \
    chmod +x /start.sh

# Set proper permissions
RUN chown -R www-data:www-data /var/airscore/tracks

EXPOSE 80

# Start with our custom script
CMD ["/start.sh"]
