// based on: https://stackoverflow.com/questions/1947537/example-code-for-memcached-in-c
#include <libmemcached/memcached.h>
#include <stdio.h>
#include <string.h>
#include <unistd.h>

#define SLEEPCT 100000

__attribute__((noreturn)) void main(int argc, char **argv) {
	memcached_server_st *servers = NULL;
	memcached_st *memc;
	memcached_return rc;
	char *cmd;
	size_t cmd_length;
	uint32_t flags;

	memc = memcached_create(NULL);
	servers = memcached_server_list_append(
		servers,
		"localhost",
		11211,
		&rc
	);
	rc = memcached_server_push(
		memc,
		servers
	);
	do
	{
		cmd = memcached_get(
			memc,
			"homePiCommand",
			13,
			&cmd_length,
			&flags,
			&rc
		);
		if (cmd_length > 0)
		{
			system(cmd);
			memcached_set(
				memc,
				"homePiCommand",
				13,
				"",
				0,
				(time_t)0,
				(uint32_t)0
			);
		}
		free(cmd);
		usleep(SLEEPCT);
	}
	while (1);
}
