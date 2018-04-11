<?php
/*
Copyright 2009-2017 John Blackbourn

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

*/

class QM_Output_Html_HTTP extends QM_Output_Html {

	public function __construct( QM_Collector $collector ) {
		parent::__construct( $collector );
		add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 90 );
		add_filter( 'qm/output/menu_class', array( $this, 'admin_class' ) );
	}

	public function output() {

		$data = $this->collector->get_data();

		$total_time = 0;

		echo '<div class="qm" id="' . esc_attr( $this->collector->id() ) . '">';

		$vars = array();

		if ( ! empty( $data['vars'] ) ) {
			foreach ( $data['vars'] as $key => $value ) {
				$vars[] = $key . ': ' . $value;
			}
		}

		if ( ! empty( $data['http'] ) ) {
			echo '<table class="qm-sortable">';

			echo '<caption class="screen-reader-text">' . esc_html__( 'HTTP API Calls', 'query-monitor' ) . '</caption>';

			echo '<thead>';
			echo '<tr>';
			echo '<th scope="col" class="qm-sorted-asc qm-sortable-column">';
			echo $this->build_sorter(); // WPCS: XSS ok.
			echo '</th>';
			echo '<th scope="col">' . esc_html__( 'Method', 'query-monitor' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'URL', 'query-monitor' ) . '</th>';
			echo '<th scope="col" class="qm-filterable-column">';
			echo $this->build_filter( 'type', array_keys( $data['types'] ), __( 'Status', 'query-monitor' ) ); // WPCS: XSS ok.
			echo '</th>';
			echo '<th scope="col">' . esc_html__( 'Caller', 'query-monitor' ) . '</th>';
			echo '<th scope="col" class="qm-filterable-column">';
			echo $this->build_filter( 'component', wp_list_pluck( $data['component_times'], 'component' ), __( 'Component', 'query-monitor' ) ); // WPCS: XSS ok.
			echo '</th>';
			echo '<th scope="col" class="qm-num qm-sortable-column">';
			echo $this->build_sorter( __( 'Timeout', 'query-monitor' ) ); // WPCS: XSS ok.
			echo '</th>';
			echo '<th scope="col" class="qm-num qm-sortable-column">';
			echo $this->build_sorter( __( 'Time', 'query-monitor' ) ); // WPCS: XSS ok.
			echo '</th>';
			echo '</tr>';
			echo '</thead>';

			echo '<tbody>';
			$i = 0;

			foreach ( $data['http'] as $key => $row ) {
				$ltime = $row['ltime'];
				$i++;
				$is_error = false;
				$row_attr = array();
				$css      = '';

				if ( is_wp_error( $row['response'] ) ) {
					$response = $row['response']->get_error_message();
					$is_error = true;
				} elseif ( ! $row['args']['blocking'] ) {
					/* translators: A non-blocking HTTP API request */
					$response = __( 'Non-blocking', 'query-monitor' );
				} else {
					$code     = wp_remote_retrieve_response_code( $row['response'] );
					$msg      = wp_remote_retrieve_response_message( $row['response'] );

					if ( intval( $code ) >= 400 ) {
						$is_error = true;
					}

					$response = $code . ' ' . $msg;

				}

				if ( $is_error ) {
					$css = 'qm-warn';
				}

				$url = self::format_url( $row['url'] );
				$info = '';

				if ( 'https' === parse_url( $row['url'], PHP_URL_SCHEME ) ) {
					if ( empty( $row['args']['sslverify'] ) && empty( $row['args']['local'] ) ) {
						$info .= '<span class="qm-warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span>' . esc_html( sprintf(
							/* translators: An HTTP API request has disabled certificate verification. 1: Relevant argument name */
							__( 'Certificate verification disabled (%s)', 'query-monitor' ),
							'sslverify=false'
						) ) . '</span><br>';
						$url = preg_replace( '|^https:|', '<span class="qm-warn">https</span>:', $url );
					} elseif ( ! $is_error && $row['args']['blocking'] ) {
						$url = preg_replace( '|^https:|', '<span class="qm-true">https</span>:', $url );
					}
				}

				$component = $row['component'];

				$stack          = array();
				$filtered_trace = $row['trace']->get_display_trace();
				array_pop( $filtered_trace ); // remove WP_Http->request()
				array_pop( $filtered_trace ); // remove WP_Http->{$method}()
				array_pop( $filtered_trace ); // remove wp_remote_{$method}()

				foreach ( $filtered_trace as $item ) {
					$stack[] = self::output_filename( $item['display'], $item['calling_file'], $item['calling_line'] );
				}

				$row_attr['data-qm-component'] = $component->name;
				$row_attr['data-qm-type']      = $row['type'];

				if ( 'core' !== $component->context ) {
					$row_attr['data-qm-component'] .= ' non-core';
				}

				$attr = '';
				foreach ( $row_attr as $a => $v ) {
					$attr .= ' ' . $a . '="' . esc_attr( $v ) . '"';
				}

				printf( // WPCS: XSS ok.
					'<tr %s class="%s">',
					$attr,
					esc_attr( $css )
				);
				printf(
					'<td class="qm-num">%s</td>',
					intval( $i )
				);
				printf(
					'<td>%s</td>',
					esc_html( $row['args']['method'] )
				);

				if ( ! empty( $row['redirected_to'] ) ) {
					$url .= sprintf(
						'<br><span class="qm-warn">%1$s</span><br>%2$s',
						/* translators: An HTTP API request redirected to another URL */
						__( 'Redirected to:', 'query-monitor' ),
						self::format_url( $row['redirected_to'] )
					);
				}

				printf( // WPCS: XSS ok.
					'<td class="qm-url qm-ltr qm-wrap">%s%s</td>',
					$info,
					$url
				);

				echo '<td class="qm-has-toggle"><div class="qm-toggler">';
				if ( $is_error ) {
					echo '<span class="dashicons dashicons-warning" aria-hidden="true"></span>';
				}
				echo esc_html( $response );
				echo self::build_toggler(); // WPCS: XSS ok;

				echo '<ul class="qm-toggled">';
				$transport = sprintf(
					/* translators: %s HTTP API transport name */
					__( 'HTTP API Transport: %s', 'query-monitor' ),
					$row['transport']
				);
				printf(
					'<li><span class="qm-info qm-supplemental">%s</span></li>',
					esc_html( $transport )
				);

				if ( ! empty( $row['info'] ) ) {
					$time_fields = array(
						'namelookup_time'    => __( 'DNS Resolution Time', 'query-monitor' ),
						'connect_time'       => __( 'Connection Time', 'query-monitor' ),
						'starttransfer_time' => __( 'Transfer Start Time (TTFB)', 'query-monitor' ),
					);
					foreach ( $time_fields as $key => $value ) {
						if ( ! isset( $row['info'][ $key ] ) ) {
							continue;
						}
						printf(
							'<li><span class="qm-info qm-supplemental">%1$s: %2$s</span></li>',
							esc_html( $value ),
							esc_html( number_format_i18n( $row['info'][ $key ], 4 ) )
						);
					}

					$size_fields = array(
						'size_download' => __( 'Response Size', 'query-monitor' ),
					);
					foreach ( $size_fields as $key => $value ) {
						if ( ! isset( $row['info'][ $key ] ) ) {
							continue;
						}
						printf(
							'<li><span class="qm-info qm-supplemental">%1$s: %2$s</span></li>',
							esc_html( $value ),
							esc_html( size_format( $row['info'][ $key ] ) )
						);
					}

					$other_fields = array(
						'content_type' => __( 'Response Content Type', 'query-monitor' ),
						'primary_ip'   => __( 'IP Address', 'query-monitor' ),
					);
					foreach ( $other_fields as $key => $value ) {
						if ( ! isset( $row['info'][ $key ] ) ) {
							continue;
						}
						printf(
							'<li><span class="qm-info qm-supplemental">%1$s: %2$s</span></li>',
							esc_html( $value ),
							esc_html( $row['info'][ $key ] )
						);
					}
				}
				echo '</ul>';
				echo '</td>';

				echo '<td class="qm-has-toggle qm-nowrap qm-ltr"><ol class="qm-toggler qm-numbered">';

				$caller = array_pop( $stack );

				if ( ! empty( $stack ) ) {
					echo self::build_toggler(); // WPCS: XSS ok;
					echo '<div class="qm-toggled"><li>' . implode( '</li><li>', $stack ) . '</li></div>'; // WPCS: XSS ok.
				}

				echo "<li>{$caller}</li>"; // WPCS: XSS ok.
				echo '</ol></td>';

				printf(
					'<td class="qm-nowrap">%s</td>',
					esc_html( $component->name )
				);
				printf(
					'<td class="qm-num">%s</td>',
					esc_html( $row['args']['timeout'] )
				);

				if ( empty( $ltime ) ) {
					$stime = '';
				} else {
					$stime = number_format_i18n( $ltime, 4 );
				}

				printf(
					'<td class="qm-num" data-qm-sort-weight="%s">%s</td>',
					esc_attr( $ltime ),
					esc_html( $stime )
				);
				echo '</tr>';
			}

			echo '</tbody>';
			echo '<tfoot>';

			$total_stime = number_format_i18n( $data['ltime'], 4 );

			echo '<tr>';
			printf(
				'<td colspan="7">%1$s<br>%2$s</td>',
				esc_html( sprintf(
					/* translators: %s: Number of HTTP API requests */
					__( 'Total Requests: %s', 'query-monitor' ),
					number_format_i18n( count( $data['http'] ) )
				) ),
				implode( '<br>', array_map( 'esc_html', $vars ) )
			);
			echo '<td class="qm-num">' . esc_html( $total_stime ) . '</td>';
			echo '</tr>';
			echo '</tfoot>';
			echo '</table>';

		} else {

			echo '<div class="qm-none">';
			echo '<p>' . esc_html__( 'None', 'query-monitor' ) . '</p>';
			echo '</div>';

		}

		echo '</div>';

	}

	public function admin_class( array $class ) {

		$data = $this->collector->get_data();

		if ( isset( $data['errors']['alert'] ) ) {
			$class[] = 'qm-alert';
		}
		if ( isset( $data['errors']['warning'] ) ) {
			$class[] = 'qm-warning';
		}

		return $class;

	}

	public function admin_menu( array $menu ) {

		$data = $this->collector->get_data();

		$count = isset( $data['http'] ) ? count( $data['http'] ) : 0;

		$title = ( empty( $count ) )
			? __( 'HTTP API Calls', 'query-monitor' )
			/* translators: %s: Number of calls to the HTTP API */
			: __( 'HTTP API Calls (%s)', 'query-monitor' );

		$args = array(
			'title' => esc_html( sprintf(
				$title,
				number_format_i18n( $count )
			) ),
		);

		if ( isset( $data['errors']['alert'] ) ) {
			$args['meta']['classname'] = 'qm-alert';
		}
		if ( isset( $data['errors']['warning'] ) ) {
			$args['meta']['classname'] = 'qm-warning';
		}

		$menu[] = $this->menu( $args );

		return $menu;

	}

}

function register_qm_output_html_http( array $output, QM_Collectors $collectors ) {
	if ( $collector = QM_Collectors::get( 'http' ) ) {
		$output['http'] = new QM_Output_Html_HTTP( $collector );
	}
	return $output;
}

add_filter( 'qm/outputter/html', 'register_qm_output_html_http', 90, 2 );
