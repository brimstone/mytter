# BUILD: defaults
# RUN:   -e DBHOST=mysql.in.the.narro.ws

FROM brimstone/ubuntu:14.04

ADD http://brimstone.github.io/fss/fss /usr/local/bin/fss

RUN chmod 755 /usr/local/bin/fss \
    && apt-get update \
    && apt-get install -y apache2 \
      libapache2-mod-php5 php5-mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists

ADD apache/mytter.conf /etc/apache2/sites-available/

ADD docker/mytter-loader /

ADD fss /fss

RUN mkdir /run/lock \
    && chmod 777 /run/lock \
    && a2enmod rewrite \
    && a2dissite 000-default.conf \
    && a2ensite mytter


EXPOSE 80

ADD www /var/www/mytter

CMD /mytter-loader
