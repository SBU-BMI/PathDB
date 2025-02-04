#!/bin/bash

LDAP_DOMAIN=hogwarts.edu
LDAP_DN=dc=hogwarts,dc=edu
LDIF_FILE=hogwarts.people.ldif
SLAPD=slapd
DOCKER_PORT=9389
DOCKER_NAME=hogwarts_ldap
DOCKER_IP=127.0.0.1

read -p "Bind method ([service_account], user, anon_user): " METHOD
METHOD=${METHOD:-service_account}

echo "Stopping all LDAP docker instances"
array=( service_account user anon_user )
for i in "${array[@]}"
do
    CID_SERVICE=`docker ps --filter "name=${DOCKER_NAME}.${i}" --format "{{.ID}}"`
    if [ $CID_SERVICE ]
        then
            docker stop $CID_SERVICE
    fi
done

LDAP_CID=`docker ps -a --filter "name=${DOCKER_NAME}.${METHOD}" --format "{{.ID}}"`
if [ $LDAP_CID ]
	then
		echo "Removing existing $DOCKER_NAME with $METHOD"
		docker rm $LDAP_CID
fi

echo "Starting $DOCKER_NAME with $METHOD"
LDAP_CID=$(docker run -e LDAP_TLS=false -e LDAP_DOMAIN="$LDAP_DOMAIN" -p $DOCKER_PORT:389 --name="${DOCKER_NAME}.${METHOD}" -d osixia/openldap)

if [ -z "$LDAP_CID" ]
	then
	echo "No LDAP CID. Exiting."
	exit
fi

docker cp $LDIF_FILE $LDAP_CID:/$LDIF_FILE
docker cp grants.${METHOD}.ldif $LDAP_CID:/grants.ldif

sleep 3
echo "Importing user and group structure"
# The admin user is provided by the docker container.
ldapadd -h $DOCKER_IP -p $DOCKER_PORT -x -D "cn=admin,$LDAP_DN" -w admin -f $LDIF_FILE

echo "Adding permissions for chosen binding method"
docker exec -it $LDAP_CID ldapmodify -Y EXTERNAL -H ldapi:/// -f /grants.ldif

echo "==================="
echo "Querying directory:"
echo "==================="
if [ "$METHOD" == "service_account" ]
    then
    echo "Searching LDAP (service account credentials)"
    ldapsearch -x -h $DOCKER_IP -p $DOCKER_PORT -b $LDAP_DN -D "cn=admin,$LDAP_DN" -w admin "(cn=hgranger)" dn
elif [ "$METHOD" == "user" ]
    then
    echo "Searching LDAP (user credentials)"
    ldapsearch -x -h $DOCKER_IP -p $DOCKER_PORT -b $LDAP_DN -D "cn=hpotter,ou=people,$LDAP_DN" -w pass "(cn=hgranger)" dn
elif [ "$METHOD" == "anon_user" ]
    then
    echo "Searching LDAP (user credentials)"
    ldapsearch -x -h $DOCKER_IP -p $DOCKER_PORT -b $LDAP_DN "(cn=hgranger)" dn
fi