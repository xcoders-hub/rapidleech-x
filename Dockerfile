FROM php:5.6-apache

# Installing packages

RUN apt-get update && \

    apt-get install -y python-pip libnet1 libnet1-dev libpcap0.8 libpcap0.8-dev git wget apache2 curl

RUN mkdir ./
RUN chmod 777 ./
WORKDIR ./
# Moving file

COPY ./ /var/www/html

# Exposing

EXPOSE  80
