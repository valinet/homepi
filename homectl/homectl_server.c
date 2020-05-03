#define _GNU_SOURCE
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <strings.h>
#include <ctype.h>

#include <linux/input.h>
#include <fcntl.h>

#include <sys/epoll.h>
#include "w_epoll.h"

#include <sys/socket.h>
#include <arpa/inet.h>

#define MAX_CONN_NUMBER 10
#define WOL_PORT 9

#define BUFSIZE 1024

struct fdlist {
	int fd;
	struct fdlist *next;
};

// https://stackoverflow.com/questions/4792035/how-do-you-validate-that-a-string-is-a-valid-mac-address-in-c
ssize_t is_valid_mac(const char* mac) {
	int i = 0;
	int s = 0;

	while (*mac) {
		if (isxdigit(*mac))
		{
			i++;
		}
		else if (*mac == ':' || *mac == '-')
		{
			if (i == 0 || i / 2 - 1 != s)
				break;
			++s;
		}
		else
		{
			s = -1;
		}
		++mac;
	}
	return (i == 12 && (s == 5 || s == 0));
}

// https://stackoverflow.com/questions/13479760/c-socket-recv-and-send-all-data
ssize_t send_all(int socket, void *buffer, size_t length)
{
	char *ptr = (char*) buffer;
	while (length > 0)
	{
		int i = send(socket, ptr, length, 0);
		if (i < 1) return -1;
		ptr += i;
		length -= i;
	}
	return 0;
}

void remove_fd(struct fdlist **fdList, int epollfd, int fd)
{
	struct fdlist *elem, *temp;
	if (w_epoll_remove_fd(epollfd, fd) < 0)
        {
        	perror("epoll remove client");
                exit(EXIT_FAILURE);
        }
        close(fd);
        elem = *fdList;
        while (elem)
        {
        	if (elem == *fdList && elem->fd == fd)
                {
                	*fdList = elem->next;
                        free(elem);
                        break;
                }
                else if (elem->next->fd == fd && elem->next->next == NULL)
                {
                        free(elem->next);
                        elem->next = NULL;
                        break;
                }
                else if (elem->next->fd == fd)
                {
                        temp = elem->next->next;
                        free(elem->next);
                        elem->next = temp;
                        break;
                }
                elem = elem->next;
        }
}

int main(int argc, char *argv[])
{
	struct fdlist *fdList = NULL, *elem, *temp;
	char buffer[BUFSIZ], *cmd = "/usr/sbin/etherwake", *args[4], mac[18], wolp[102];
	int fd, epollfd, devfd, sockfd, clientfd, sock_opt = 1, len, i, wolsockfd;
	struct input_event event_data;
        struct epoll_event rev;
	struct sockaddr_in serv_addr, wol_addr;
	struct sockaddr_in client_addr;
	struct timeval tv;
	socklen_t addrlen = sizeof(struct sockaddr_in);
	pid_t pid;

#ifdef FORK_ETHERWAKE
	args[0] = cmd;
	args[1] = "-i";
	args[2] = "eth0";
	args[3] = mac;
#endif
	mac[17] = 0;
	if ((fd = open(argv[3], O_RDONLY)) < 0)
	{
		perror("open");
		exit(EXIT_FAILURE);
	}
	if (read(fd, mac, 17) < 0)
	{
		perror("read");
		exit(EXIT_FAILURE);
	}
	if (!is_valid_mac(mac))
	{
		perror("is_valid_mac");
		exit(EXIT_FAILURE);
	}
	close(fd);
#ifndef FORK_ETHERWAKE
	for (i = 0; i < 18; ++i)
	{
		if (mac[i] == ':') mac[i] = 0;
	}
	wol_addr.sin_family = AF_INET;
        wol_addr.sin_port = WOL_PORT;
	wol_addr.sin_addr.s_addr = INADDR_BROADCAST;
#endif

	bzero(buffer, BUFSIZ);
	bzero((char *) &serv_addr, sizeof(serv_addr));
        serv_addr.sin_addr.s_addr = INADDR_ANY;
        serv_addr.sin_family = AF_INET;
        serv_addr.sin_port = htons(atoi(argv[1]));

	if ((devfd = open(argv[2], O_RDONLY | O_CLOEXEC)) == -1)
	{
		perror("opening device");
		exit(EXIT_FAILURE);
	}
	if ((sockfd = socket(AF_INET, SOCK_STREAM | SOCK_NONBLOCK | SOCK_CLOEXEC, 0)) < 0)
	{
		perror("creating socket");
		exit(EXIT_FAILURE);
	}
	if (setsockopt(sockfd, SOL_SOCKET, SO_REUSEADDR, &sock_opt, sizeof(int)) < 0)
	{
		perror("setsockopt");
		exit(EXIT_FAILURE);
	}
#ifndef FORK_ETHERWAKE
	if ((wolsockfd = socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP)) < 0)
	{
		perror("creating wol socket");
		exit(EXIT_FAILURE);
	}
	if (setsockopt(wolsockfd, SOL_SOCKET, SO_BROADCAST, &sock_opt, sizeof(int)) < 0)
	{
		perror("setsockopt wol");
		exit(EXIT_FAILURE);
	}
#endif
	if (bind(sockfd, (const struct sockaddr*)&serv_addr, addrlen) < 0)
	{
		perror("bind");
		exit(EXIT_FAILURE);
	}
	if (listen(sockfd, MAX_CONN_NUMBER) < 0)
	{
		perror("listen");
		exit(EXIT_FAILURE);
	}
	if ((epollfd = w_epoll_create(EPOLL_CLOEXEC)) < 0)
	{
		perror("epoll create");
		exit(EXIT_FAILURE);
	}
	if (w_epoll_add_fd_in(epollfd, devfd) < 0)
	{
		perror("epoll add devfd");
		exit(EXIT_FAILURE);
	}
        if (w_epoll_add_fd_in(epollfd, sockfd) < 0)
        {
                perror("epoll add sockfd");
                exit(EXIT_FAILURE);
        }
	while (1) {
	        if (w_epoll_wait_infinite(epollfd, &rev) < 0)
        	{
			perror("epoll wait");
			exit(EXIT_FAILURE);
        	}
		if (
			rev.data.fd == devfd &&
			rev.events == EPOLLIN &&
			read(devfd, &event_data, sizeof(event_data)) &&
			((event_data.type == EV_REL &&
			event_data.code == REL_WHEEL) ||
			(event_data.type == EV_KEY &&
			(event_data.code == BTN_LEFT || event_data.code == BTN_RIGHT || event_data.code == BTN_MIDDLE)))
		)
		{
                        if (event_data.type == EV_KEY && event_data.value == 0 && event_data.code == BTN_MIDDLE)
                        {
#ifdef FORK_ETHERWAKE
        	                pid = fork();
                                if (pid == -1)
                                {
                	                perror("fork\n");
                                        exit(EXIT_FAILURE);
                                }
                        	if (!pid)
                                {
                                	execvp(cmd, args);
                                }
#else
				for (i = 0; i < 6; ++i)
				{
					wolp[i] = 0xFF;
				}
				for (i = 6; i < 6 + 6 * 16; ++i)
				{
					wolp[i] = strtol(mac + ((i - 6) % 6) * 3, NULL, 16);
				}
				if (sendto(wolsockfd, wolp, sizeof(wolp), 0, (struct sockaddr*)(&wol_addr), sizeof(wol_addr)) < 0)
				{
					printf("sendto error\n");
				}
#endif
	                }
			elem = fdList;
			while (elem)
                        {
				if (event_data.type == EV_REL && event_data.code == REL_WHEEL)
				{
					sprintf(buffer, "%ld whl %d\n", event_data.time.tv_sec, event_data.value);
				}
				else if (event_data.type == EV_KEY && event_data.value == 0)
				{
					sprintf(buffer, "%ld btn %d\n", event_data.time.tv_sec, event_data.code - BTN_LEFT);
				}
				len = strlen(buffer);
				//printf("%s", buffer);
				if (send_all(elem->fd, buffer, len) < 0)
				{
					temp = elem->next;
					remove_fd(&fdList, epollfd, elem->fd);
					bzero(buffer, len);
					elem = temp;
					continue;
	      	        	}
				bzero(buffer, len);
                                elem = elem->next;
                        }
		}
		else if (
			rev.data.fd == sockfd &&
			rev.events == EPOLLIN
		)
		{
			if ((clientfd = accept4(sockfd, (struct sockaddr*)&client_addr, &addrlen, SOCK_CLOEXEC)) < 0)
			{
		                perror("socket accept");
				exit(EXIT_FAILURE);
			}
			if (setsockopt(clientfd, SOL_SOCKET, SO_REUSEADDR, &sock_opt, sizeof(int)) < 0)
			{
		                perror("setsockopt client");
				exit(EXIT_FAILURE);
			}
			gettimeofday(&tv, NULL);
                        sprintf(buffer, "%ld snk\n", tv.tv_sec);
                        len = strlen(buffer);
                        //printf("%s", buffer);
                        if (send_all(clientfd, buffer, len) < 0)
                        {
                                perror("send_all snk");
                                close(clientfd);
				continue;
			}
			if (w_epoll_add_fd_in(epollfd, clientfd) < 0)
			{
		                perror("epoll add client");
				exit(EXIT_FAILURE);
			}
			elem = malloc(sizeof(struct fdlist));
			if (elem == NULL)
			{
                                perror("malloc elem");
                                exit(EXIT_FAILURE);
                        }
			elem->fd = clientfd;
			if (fdList == NULL)
			{
				elem->next = NULL;
				fdList = elem;
			}
			else
			{
				elem->next = fdList;
				fdList = elem;
			}
		}
		else if (rev.data.fd != devfd && rev.events == EPOLLIN)
		{
			if (recv(rev.data.fd, buffer, BUFSIZE, 0) <= 0)
			{
				remove_fd(&fdList, epollfd, rev.data.fd);
			}
		}
	}
	perror("exit\n");
	exit(EXIT_FAILURE);
	return 0;
}
