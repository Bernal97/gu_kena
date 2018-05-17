<?php
class cron {
    function __construct() {
        $fecha = '2018-05-22';
            //Genera un JSON de total rector
            $this->datos_rector($fecha);

            //Genera 4 JSONS de total rector por claustro
            $this->datos_rector_claustro($fecha);
            //Genera 4 JSONS de total consejero superior por claustro
            $this->datos_sup_claustro($fecha);

            //Genera 18 JSONS de total rector por unidad electoral
            $this->datos_rector_ue($fecha);
            //Genera 17 JSONS de total decano por unidad electoral
            $this->datos_decano_ue($fecha);

            //Genera 17*4 + 1 = 69 JSONS de total rector por claustro y por unidad electoral
            $this->datos_ue_claustro($fecha, 'voto_lista_rector', 'lista_rector', 'Rector', 'R');
            //Genera 17*4 = 68 JSONS de total decano por claustro y por unidad electoral
            $this->datos_ue_claustro($fecha, 'voto_lista_decano', 'lista_decano', 'Decano', 'D');
            //Genera 17*4 = 68 JSONS de total consejo superior por claustro y por unidad electoral
            $this->datos_ue_claustro($fecha, 'voto_lista_csuperior', 'lista_csuperior', 'Consejero Superior', 'CS');
            //Genera 17*4 = 68 JSONS de total consejo directivo por claustro y por unidad electoral
            $this->datos_ue_claustro($fecha, 'voto_lista_cdirectivo', 'lista_cdirectivo', 'Consejero Directivo', 'CD');
        }

        //Metodo que calcula y genera JSONS de la categoria $categoria para cada unidad
        //electoral y cada claustro
        function datos_ue_claustro($fecha, $tabla_voto, $tabla_lista, $categoria, $sigla_cat){
            $cargos = '';
            if($sigla_cat == 'CS'){
                $cargos = ", cl.cargos_csuperior as cant_cargos";
            }elseif($sigla_cat == 'CD'){
                $cargos = ", case ue.nivel when 2 then cl.cargos_cdirectivo 
                                           when 3 then cl.cargos_cdiras end as cant_cargos";
            }

            $sql = "
                select datos.*, 
                case when m_enviadas is null then 0 else m_enviadas end as m_enviadas, 
                case when m_confirmadas is null then 0 else m_confirmadas end as m_confirmadas, 
                m_total
                from (
                    select ue.nombre as unidad_electoral, ue.sigla as sigla_ue, 
                        ue.id_nro_ue as id_ue,
                        cl.descripcion as claustro, cl.id id_claustro,
                        l.id_nro_lista, l.nombre as lista,
                        s.sigla as sede, m.nro_mesa,
                        l.sigla as sigla_lista, vl.cant_votos, total.total $cargos
                    from acta a 
                    inner join mesa m on m.id_mesa = a.de
                    inner join claustro cl on cl.id = m.id_claustro
                    inner join $tabla_voto vl on vl.id_acta = a.id_acta
                    inner join $tabla_lista l on l.id_nro_lista = vl.id_lista
                    inner join sede s on s.id_sede = a.id_sede
                    inner join unidad_electoral ue on ue.id_nro_ue = s.id_ue
                    inner join (
                        select m.id_claustro, s.id_ue,
                                vl.id_lista,  
                                sum(vl.cant_votos) total
                        from acta a 
                        inner join mesa m on m.id_mesa = a.de
                        inner join $tabla_voto vl on vl.id_acta = a.id_acta
                        inner join sede s on s.id_sede = a.id_sede
                        where m.fecha = '".$fecha."'
                        group by s.id_ue, m.id_claustro, vl.id_lista 
                    ) total on total.id_claustro = cl.id
                            and total.id_lista = l.id_nro_lista
                            and total.id_ue = s.id_ue
                    where l.fecha = '".$fecha."'
                    order by unidad_electoral, claustro, lista, sede 
                ) datos
                inner join (select count(distinct(m.id_mesa)) as m_total, s.id_ue, m.id_claustro 
                    from mesa m
                    inner join acta a on a.de = m.id_mesa
                    inner join sede s on s.id_sede = a.id_sede
                    where m.fecha = '$fecha'
                    group by s.id_ue, m.id_claustro) t on t.id_ue = datos.id_ue 
                                            and t.id_claustro = datos.id_claustro
                left join (select count(distinct(m.id_mesa)) as m_confirmadas, s.id_ue, m.id_claustro from mesa m
                    inner join acta a on a.de = m.id_mesa
                    inner join sede s on s.id_sede = a.id_sede
                    where m.fecha = '$fecha' and m.estado>2
                    group by s.id_ue, m.id_claustro) m2 on m2.id_ue = t.id_ue
                                            and m2.id_claustro = t.id_claustro
                left join (select count(distinct(m.id_mesa)) as m_enviadas, s.id_ue, m.id_claustro from mesa m
                    inner join acta a on a.de = m.id_mesa
                    inner join sede s on s.id_sede = a.id_sede
                    where m.fecha = '$fecha' and m.estado>1
                    group by s.id_ue, m.id_claustro) m on m.id_ue = t.id_ue
                                            and m2.id_claustro = t.id_claustro
                    ";
            $datos = toba::db('gu_kena')->consultar($sql);

            $nom_ue = null;
            $nom_claustro = null;
            $nom_lista = null;
            $sedes = array();
            $columns_sedes = array();

            $data = array();
            $labels = array();
            $total = array();
            $lista = array();
            $cant_cargos = null;//Util para el calculo dhont en superior y directivo

            //Datos de mesas
            $m_enviadas = null;
            $m_confirmadas = null;
            $m_total = null;

            foreach($datos as $un_registro){
                if($nro_lista != null && $nro_lista != $un_registro['id_nro_lista']){
                    $labels[] = $lista['sigla_lista'];
                    $total[] = $lista['total'];

                    $r = array();
                    $r['lista'] = utf8_encode(trim($lista['lista']));
                    $r['sigla_lista'] = trim($lista['sigla_lista']);

                    foreach($sedes as $sigla_sede => $cant_votos){
                        $r[$sigla_sede] = $cant_votos;
                        $columns_sedes[$sigla_sede] = $sigla_sede;
                    }
                    $r['total'] = $lista['total'];
                    $data[] = $r;

                    if(($nom_ue != null && $nom_ue != $un_registro['sigla_ue'])
                            || ($nom_claustro != null && $nom_claustro != $un_registro['claustro'])){
                        if(sizeof($data) > 0){//Solo si existen datos ent crea el json
                            $json = array();
                            $columns = array();
                            $columns[] = array('field' => 'lista', 'title' => 'Listas');
                            $columns[] = array('field' => 'sigla_lista', 'title' => 'Sigla Listas');
                            foreach($columns_sedes as $key => $sigla_sede){
                                $columns[] = array('field' => $key, 'title' => $sigla_sede);
                            }
                            $columns[] = array('field' => 'total', 'title' => 'Total');

                            $json['columns'] = $columns;
                            $json['data'] = $data;
                            $json['labels'] = $labels;
                            $json['total'] = $total;

                            //Calculo dhont solo para cons. superior y directivo, los 
                            // unicos que tendran este campo cargado
                            if(isset($cant_cargos)){
                                $res = $this->dhont($labels, $total, $cant_cargos);
                                $json['data2'] = $res[1];
                                $json['columns2'] = $res[0];
                            }

                            $json['fecha'] = date('d/m/Y G:i:s');
                            $json['titulo'] = 'Votos '.$nom_ue.' '.$categoria.' '.$nom_claustro;

                            $json['enviadas'] = $m_enviadas." enviadas de ".$m_total;
                            $json['confirmadas'] = $m_confirmadas." confirmadas de ".$m_total;

                            $string_json = json_encode($json);
                            $nom_archivo = 'e'.str_replace('-','',$fecha).'/'.$sigla_cat.'_'.strtoupper($nom_ue).'_'.strtoupper($nom_claustro[0]).'.json';
                            file_put_contents('resultados_json/'. $nom_archivo , $string_json);
                        }
                        $data = array();
                        $labels = array();
                        $total = array();
                        $columns_sedes = array();

                        $nom_ue = $un_registro['sigla_ue'];  
                        $nom_claustro = $un_registro['claustro'];
                        $lista = array();
                        $sedes = array();
                    }elseif($nom_ue == null){
                        $nom_ue = $un_registro['sigla_ue'];
                        $nom_claustro = $un_registro['claustro'];
                    }

                    $lista = array();
                    $lista['lista'] = $un_registro['lista'];
                    $lista['sigla_lista'] = $un_registro['sigla_lista'];
                    $lista['total'] = $un_registro['total'];
                    $nro_lista = $un_registro['id_nro_lista'];
                    $sedes = array();

                }elseif($nro_lista == null){
                    $lista['lista'] = $un_registro['lista'];
                    $lista['sigla_lista'] = $un_registro['sigla_lista'];
                    $lista['total'] = $un_registro['total'];
                    $nro_lista = $un_registro['id_nro_lista'];

                    $nom_ue = $un_registro['sigla_ue'];
                    $nom_claustro = $un_registro['claustro'];
                }

                $sedes[$un_registro['sede'].' mesa '.$un_registro['nro_mesa']] = $un_registro['cant_votos'];
                $cant_cargos = $un_registro['cant_cargos'];

                //Datos de mesas
                $m_enviadas = $un_registro['m_enviadas'];
                $m_confirmadas = $un_registro['m_confirmadas'];
                $m_total = $un_registro['m_total'];
            }

            if(sizeof($data) > 0){//Solo si existen datos finales ent crea el json
                $json = array();
                $columns = array();
                $columns[] = array('field' => 'lista', 'title' => 'Listas');
                $columns[] = array('field' => 'sigla_lista', 'title' => 'Sigla Listas');
                foreach($columns_sedes as $key => $sigla_sede){
                    $columns[] = array('field' => $key, 'title' => $sigla_sede);
                }
                $columns[] = array('field' => 'total', 'title' => 'Total');

                $json['columns'] = $columns;
                $json['data'] = $data;
                $json['labels'] = $labels;
                $json['total'] = $total;

                //Calculo dhont solo para cons. superior y directivo, los 
                // unicos que tendran este campo cargado
                if(isset($cant_cargos)){
                    $res = $this->dhont($labels, $total, $cant_cargos);
                    $json['data2'] = $res[1];
                    $json['columns2'] = $res[0];
                }

                $json['fecha'] = date('d/m/Y G:i:s');
                $json['titulo'] = 'Votos '.$nom_ue.' '.$categoria.' '.$nom_claustro;

                $json['enviadas'] = $m_enviadas." enviadas de ".$m_total;
                $json['confirmadas'] = $m_confirmadas." confirmadas de ".$m_total;

                $string_json = json_encode($json);
                $nom_archivo = 'e'.str_replace('-','',$fecha).'/'.$sigla_cat.'_'.strtoupper($nom_ue).'_'.strtoupper($nom_claustro[0]).'.json';
                file_put_contents('resultados_json/'. $nom_archivo , $string_json);
            }
        }

        //Metodo que calcula y genera archivos JSONS ubicados en /resultados_json/$fecha
        //con datos de resultados rector por cada unidad electoral
        function datos_rector_ue($fecha){
            $sql = "
                select datos.*, 
                case when m_enviadas is null then 0 else m_enviadas end as m_enviadas, 
                case when m_confirmadas is null then 0 else m_confirmadas end as m_confirmadas, 
                m_total
                from (
                    select ue.nombre as unidad_electoral, ue.sigla as sigla_ue,
                        ue.id_nro_ue as id_ue,
                        trim(cl.descripcion) as claustro, 
                        l.id_nro_lista, trim(l.nombre) as lista,
                        s.sigla as sede, m.nro_mesa,
                        trim(l.sigla) as sigla_lista, vl.cant_votos, total.total
                    from acta a 
                    inner join mesa m on m.id_mesa = a.de
                    inner join claustro cl on cl.id = m.id_claustro
                    inner join voto_lista_rector vl on vl.id_acta = a.id_acta
                    inner join lista_rector l on l.id_nro_lista = vl.id_lista
                    inner join sede s on s.id_sede = a.id_sede
                    inner join unidad_electoral ue on ue.id_nro_ue = s.id_ue
                    inner join (
                        select m.id_claustro, s.id_ue,
                                vl.id_lista,  
                                sum(vl.cant_votos) total
                        from acta a 
                        inner join mesa m on m.id_mesa = a.de
                        inner join voto_lista_rector vl on vl.id_acta = a.id_acta
                        inner join sede s on s.id_sede = a.id_sede
                        where m.fecha = '".$fecha."'
                        group by s.id_ue, m.id_claustro, vl.id_lista 
                    ) total on total.id_claustro = cl.id
                            and total.id_lista = l.id_nro_lista
                            and total.id_ue = s.id_ue
                    where l.fecha = '".$fecha."'
                    order by unidad_electoral, claustro, lista, sede
                ) datos
                inner join (select count(distinct(m.id_mesa))  as m_total, s.id_ue from mesa m
                    inner join acta a on a.de = m.id_mesa
                    inner join sede s on s.id_sede = a.id_sede
                    where m.fecha = '$fecha'
                    group by s.id_ue) t on t.id_ue = datos.id_ue
                left join (select count(distinct(m.id_mesa)) as m_confirmadas, s.id_ue from mesa m
                    inner join acta a on a.de = m.id_mesa
                    inner join sede s on s.id_sede = a.id_sede
                    where m.fecha = '$fecha' and m.estado>2
                    group by s.id_ue) m2 on m2.id_ue = t.id_ue
                left join (select count(distinct(m.id_mesa)) as m_enviadas, s.id_ue from mesa m
                    inner join acta a on a.de = m.id_mesa
                    inner join sede s on s.id_sede = a.id_sede
                    where m.fecha = '$fecha' and m.estado>1
                    group by s.id_ue) m on m.id_ue = t.id_ue
                    ";
            $datos = toba::db('gu_kena')->consultar($sql);

            $nom_ue = null;
            $data = array();
            $labels = array();
            $total = array();
            $claustros = array();

            //Datos de mesas
            $m_enviadas = null;
            $m_confirmadas = null;
            $m_total = null;
            foreach($datos as $un_registro){
                if($nom_ue != null && $nom_ue != $un_registro['sigla_ue']){
                    $json = array();

                    $columns = array();
                    $columns[] = array('field' => 'lista', 'title' => 'Listas');
                    $columns[] = array('field' => 'sigla_lista', 'title' => 'Sigla Listas');
                    foreach($claustros as $key => $value){
                        $columns[] = array('field' => $key, 'title' => $key);
                    }
                    $columns[] = array('field' => 'total', 'title' => 'Total');

                    $fila_total = array();//Ultima fila que contiene los totales de cada columna
                    $fila_total['lista'] = 'TOTAL'; 
                    foreach($data as $key => $value){
                        foreach($claustros as $k => $v)
                            $fila_total[$k] += $value[$k];
                        $fila_total['total'] += $value['total'];

                        $json['data'][] = $value;
                        $json['labels'][] = $value['sigla_lista'];
                        $json['total'][] = $value['total'];
                    }
                    $json['data'][] = $fila_total;
                    $json['columns'] = $columns;
                    $json['fecha'] = date('d/m/Y G:i:s');
                    if(strtoupper($nom_ue) == 'RECT')
                        $json['titulo'] = 'Votos Adm. Central Rector';
                    else
                        $json['titulo'] = 'Votos '.$nom_ue.' Rector';

                    $json['enviadas'] = $m_enviadas." enviadas de ".$m_total;
                    $json['confirmadas'] = $m_confirmadas." confirmadas de ".$m_total;

                    $data = array();
                    $claustros = array();
                    $string_json = json_encode($json);
                    $nom_archivo = 'e'.str_replace('-','',$fecha).'/R_'.strtoupper($nom_ue).'_T.json';
                    file_put_contents('resultados_json/'. $nom_archivo , $string_json);

                    $nom_ue = $un_registro['sigla_ue'];                
                }elseif($nom_ue == null)
                    $nom_ue = $un_registro['sigla_ue'];

                $data[$un_registro['sigla_lista']]['lista'] = utf8_encode($un_registro['lista']);
                $data[$un_registro['sigla_lista']]['sigla_lista'] = utf8_encode($un_registro['sigla_lista']);
                $data[$un_registro['sigla_lista']]['total'] += $un_registro['total'];
                $data[$un_registro['sigla_lista']][$un_registro['claustro']] = $un_registro['cant_votos'];

                $claustros[$un_registro['claustro']] = $un_registro['claustro'];
                //Datos de mesas
                $m_enviadas = $un_registro['m_enviadas'];
                $m_confirmadas = $un_registro['m_confirmadas'];
                $m_total = $un_registro['m_total'];
            }

            if(isset($data) && $nom_ue != null){//Quedo un ultimo claustro sin guardar
                $json = array();

                $columns = array();
                $columns[] = array('field' => 'lista', 'title' => 'Listas');
                $columns[] = array('field' => 'sigla_lista', 'title' => 'Sigla Listas');
                foreach($claustros as $key => $value){
                    $columns[] = array('field' => $key, 'title' => $key);
                }
                $columns[] = array('field' => 'total', 'title' => 'Total');

                $fila_total = array();//Ultima fila que contiene los totales de cada columna
                $fila_total['lista'] = 'TOTAL'; 
                foreach($data as $key => $value){
                    foreach($claustros as $k => $v)
                        $fila_total[$k] += $value[$k];
                    $fila_total['total'] += $value['total'];

                    $json['data'][] = $value;
                    $json['labels'][] = $value['sigla_lista'];
                    $json['total'][] = $value['total'];
                }
                $json['data'][] = $fila_total;
                $json['columns'] = $columns;
                $json['fecha'] = date('d/m/Y G:i:s');
                if(strtoupper($nom_ue) == 'RECT')
                    $json['titulo'] = 'Votos Adm. Central Rector';
                else
                    $json['titulo'] = 'Votos '.$nom_ue.' Rector';

                $json['enviadas'] = $m_enviadas." enviadas de ".$m_total;
                $json['confirmadas'] = $m_confirmadas." confirmadas de ".$m_total;

                $string_json = json_encode($json);
                $nom_archivo = 'e'.str_replace('-','',$fecha).'/R_'.strtoupper($nom_ue).'_T.json';
                file_put_contents('resultados_json/'. $nom_archivo , $string_json);
            }
        }

        //Metodo que calcula y genera archivos JSONS ubicados en /resultados_json/$fecha
        //con datos de resultados decano por cada unidad electoral
        function datos_decano_ue($fecha){
            $sql = "
               select datos.*, 
                case when m_enviadas is null then 0 else m_enviadas end as m_enviadas, 
                case when m_confirmadas is null then 0 else m_confirmadas end as m_confirmadas, 
                m_total
                from (
                    select ue.nombre as unidad_electoral, ue.sigla as sigla_ue, 
                        ue.id_nro_ue as id_ue, 
                        trim(cl.descripcion) as claustro, 
                        l.id_nro_lista, trim(l.nombre) as lista,
                        s.sigla as sede, m.nro_mesa,
                        trim(l.sigla) as sigla_lista, vl.cant_votos, total.total
                    from acta a 
                    inner join mesa m on m.id_mesa = a.de
                    inner join claustro cl on cl.id = m.id_claustro
                    inner join voto_lista_decano vl on vl.id_acta = a.id_acta
                    inner join lista_decano l on l.id_nro_lista = vl.id_lista
                    inner join sede s on s.id_sede = a.id_sede
                    inner join unidad_electoral ue on ue.id_nro_ue = s.id_ue
                    inner join (
                        select m.id_claustro, s.id_ue,
                                vl.id_lista,  
                                sum(vl.cant_votos) total
                        from acta a 
                        inner join mesa m on m.id_mesa = a.de
                        inner join voto_lista_decano vl on vl.id_acta = a.id_acta
                        inner join sede s on s.id_sede = a.id_sede
                        where m.fecha = '".$fecha."'
                        group by s.id_ue, m.id_claustro, vl.id_lista 
                    ) total on total.id_claustro = cl.id
                            and total.id_lista = l.id_nro_lista
                            and total.id_ue = s.id_ue
                    where l.fecha = '".$fecha."'
                    order by unidad_electoral, claustro, lista, sede
                ) datos
                inner join (select count(distinct(m.id_mesa))  as m_total, s.id_ue from mesa m
                    inner join acta a on a.de = m.id_mesa
                    inner join sede s on s.id_sede = a.id_sede
                    where m.fecha = '$fecha'
                    group by s.id_ue) t on t.id_ue = datos.id_ue
                left join (select count(distinct(m.id_mesa)) as m_confirmadas, s.id_ue from mesa m
                    inner join acta a on a.de = m.id_mesa
                    inner join sede s on s.id_sede = a.id_sede
                    where m.fecha = '$fecha' and m.estado>2
                    group by s.id_ue) m2 on m2.id_ue = t.id_ue
                left join (select count(distinct(m.id_mesa)) as m_enviadas, s.id_ue from mesa m
                    inner join acta a on a.de = m.id_mesa
                    inner join sede s on s.id_sede = a.id_sede
                    where m.fecha = '$fecha' and m.estado>1
                    group by s.id_ue) m on m.id_ue = t.id_ue
                    ";
            $datos = toba::db('gu_kena')->consultar($sql);

            $nom_ue = null;
            $data = array();
            $labels = array();
            $total = array();
            $claustros = array();

            //Datos de mesas
            $m_enviadas = null;
            $m_confirmadas = null;
            $m_total = null;
            foreach($datos as $un_registro){
                if($nom_ue != null && $nom_ue != $un_registro['sigla_ue']){
                    $json = array();

                    $columns = array();
                    $columns[] = array('field' => 'lista', 'title' => 'Listas');
                    $columns[] = array('field' => 'sigla_lista', 'title' => 'Sigla Listas');
                    foreach($claustros as $key => $value){
                        $columns[] = array('field' => $key, 'title' => $key);
                    }
                    $columns[] = array('field' => 'total', 'title' => 'Total');

                    $fila_total = array();//Ultima fila que contiene los totales de cada columna
                    $fila_total['lista'] = 'TOTAL'; 
                    foreach($data as $key => $value){
                        foreach($claustros as $k => $v)
                            $fila_total[$k] += $value[$k];
                        $fila_total['total'] += $value['total'];

                        $json['data'][] = $value;
                        $json['labels'][] = $value['sigla_lista'];
                        $json['total'][] = $value['total'];
                    }
                    $json['data'][] = $fila_total;
                    $json['columns'] = $columns;
                    $json['fecha'] = date('d/m/Y G:i:s');
                    if(strtoupper($nom_ue) == 'RECT')
                        $json['titulo'] = 'Votos Adm. Central Rector';
                    else
                        $json['titulo'] = 'Votos '.$nom_ue.' Rector';

                    $json['enviadas'] = $m_enviadas." enviadas de ".$m_total;
                    $json['confirmadas'] = $m_confirmadas." confirmadas de ".$m_total;

                    $data = array();
                    $claustros = array();
                    $string_json = json_encode($json);
                    $nom_archivo = 'e'.str_replace('-','',$fecha).'/D_'.strtoupper($nom_ue).'_T.json';
                    file_put_contents('resultados_json/'. $nom_archivo , $string_json);

                    $nom_ue = $un_registro['sigla_ue'];                
                }elseif($nom_ue == null)
                    $nom_ue = $un_registro['sigla_ue'];

                $data[$un_registro['sigla_lista']]['lista'] = utf8_encode($un_registro['lista']);
                $data[$un_registro['sigla_lista']]['sigla_lista'] = utf8_encode($un_registro['sigla_lista']);
                $data[$un_registro['sigla_lista']]['total'] += $un_registro['total'];
                $data[$un_registro['sigla_lista']][$un_registro['claustro']] = $un_registro['cant_votos'];

                $claustros[$un_registro['claustro']] = $un_registro['claustro'];

                //Datos de mesas
                $m_enviadas = $un_registro['m_enviadas'];
                $m_confirmadas = $un_registro['m_confirmadas'];
                $m_total = $un_registro['m_total'];
            }

            if(isset($data) && $nom_ue != null){//Quedo un ultimo claustro sin guardar
                $json = array();

                $columns = array();
                $columns[] = array('field' => 'lista', 'title' => 'Listas');
                $columns[] = array('field' => 'sigla_lista', 'title' => 'Sigla Listas');
                foreach($claustros as $key => $value){
                    $columns[] = array('field' => $key, 'title' => $key);
                }
                $columns[] = array('field' => 'total', 'title' => 'Total');

                $fila_total = array();//Ultima fila que contiene los totales de cada columna
                $fila_total['lista'] = 'TOTAL'; 
                foreach($data as $key => $value){
                    foreach($claustros as $k => $v)
                        $fila_total[$k] += $value[$k];
                    $fila_total['total'] += $value['total'];

                    $json['data'][] = $value;
                    $json['labels'][] = $value['sigla_lista'];
                    $json['total'][] = $value['total'];
                }
                $json['data'][] = $fila_total;
                $json['columns'] = $columns;
                $json['fecha'] = date('d/m/Y G:i:s');
                if(strtoupper($nom_ue) == 'RECT')
                    $json['titulo'] = 'Votos Adm. Central Rector';
                else
                    $json['titulo'] = 'Votos '.$nom_ue.' Rector';

                $json['enviadas'] = $m_enviadas." enviadas de ".$m_total;
                $json['confirmadas'] = $m_confirmadas." confirmadas de ".$m_total;

                $string_json = json_encode($json);
                $nom_archivo = 'e'.str_replace('-','',$fecha).'/D_'.strtoupper($nom_ue).'_T.json';
                file_put_contents('resultados_json/'. $nom_archivo , $string_json);
            }
        }

        //Metodo que calcula y genera archivos JSONS ubicados en /resultados_json/$fecha
        //con datos de resultados consejero superior por cada claustro
        function datos_sup_claustro($fecha){
            $sql = "
                 select datos.*, 
                case when m_enviadas is null then 0 else m_enviadas end as m_enviadas, 
                case when m_confirmadas is null then 0 else m_confirmadas end as m_confirmadas, 
                m_total
                from (
                    select claustro, id_claustro,
                        trim(lista) as lista, trim(sigla_lista) as sigla_lista, 
                        cargos_csuperior as cant_cargos,
                        sum(ponderado) ponderado
                    from(
                        select votos_totales.id_tipo, votos_totales.id_nro_ue, 
                            votos_totales.sigla as sigla_ue, votos_totales.id_claustro, votos_totales.claustro, 
                            votos_totales.id_nro_lista, votos_totales.lista, votos_totales.sigla_lista, 
                            votos_totales.total, empadronados.empadronados, votos_totales.cargos_csuperior,
                            case when empadronados.empadronados <> 0 then 
                                    votos_totales.total/cast(empadronados.empadronados as decimal) 
                            end ponderado 
                        from (select a.id_tipo, ue.id_nro_ue, ue.sigla, 
                            m.id_claustro as id_claustro, c.descripcion claustro, 
                            l.id_nro_lista, l.nombre lista, c.cargos_csuperior,
                            l.sigla sigla_lista, sum(cant_votos) total 
                            from acta a 
                            inner join mesa m on m.id_mesa = a.de 
                            inner join sede s on s.id_sede = a.id_sede 
                            inner join unidad_electoral ue on ue.id_nro_ue = s.id_ue 
                            inner join claustro c on c.id = m.id_claustro 
                            inner join voto_lista_csuperior vl on vl.id_acta = a.id_acta 
                            inner join lista_csuperior l on l.id_nro_lista = vl.id_lista 
                            where m.estado > 1 and m.fecha = '".$fecha."' 
                            group by ue.id_nro_ue, ue.sigla, c.descripcion, l.nombre, l.id_nro_lista, l.sigla, s.id_ue, 
                                m.id_claustro, a.id_tipo, c.cargos_csuperior  
                        order by s.id_ue,m.id_claustro, l.nombre 
                    ) votos_totales
                    inner join (select id_tipo, id_ue, ue.sigla, id_claustro, sum(cant_empadronados) empadronados 
                            from sede s 
                            inner join acta a on a.id_sede = s.id_sede
                            inner join mesa m on m.id_mesa = a.de 
                            inner join unidad_electoral ue on ue.id_nro_ue = s.id_ue 
                            where m.fecha = '".$fecha."' 
                            group by id_ue, id_claustro, ue.sigla, id_tipo 
                    ) empadronados on empadronados.id_ue = votos_totales.id_nro_ue 
                                and empadronados.id_claustro = votos_totales.id_claustro
                                and empadronados.id_tipo = votos_totales.id_tipo
                    ) t
                    group by id_tipo, claustro, id_claustro, lista, sigla_lista, cargos_csuperior
                    order by claustro, lista
                ) datos
                inner join (
                    select count(*)  as m_total, m.id_claustro from mesa m
                    where m.fecha = '$fecha'
                    group by m.id_claustro) t on t.id_claustro = datos.id_claustro
                left join (
                    select count(*) as m_confirmadas, m.id_claustro from mesa m
                    where m.fecha = '$fecha' and m.estado>2
                    group by m.id_claustro) m2 on m2.id_claustro = t.id_claustro
                left join (
                    select count(*) as m_enviadas, m.id_claustro from mesa m
                    where m.fecha = '$fecha' and m.estado>1
                    group by m.id_claustro) m on m.id_claustro = t.id_claustro
                    ";
            $datos = toba::db('gu_kena')->consultar($sql);

            $columns = array();
            $columns[] = array('field' => 'lista', 'title' => 'Listas');
            $columns[] = array('field' => 'sigla_lista', 'title' => 'Sigla Listas');
            $columns[] = array('field' => 'ponderado', 'title' => 'Ponderado');

            $nom_claustro = null;
            $cant_cargos =null;
            $data = array();
            $data2 = array();
            $columns2 = array();

            //Datos de mesas
            $m_enviadas = null;
            $m_confirmadas = null;
            $m_total = null;
            foreach($datos as $un_registro){
                if($nom_claustro != null && $nom_claustro != $un_registro['claustro']){
                    $json = array();
                    $json['columns'] = $columns;
                    $json['data'] = $data;
                    $json['labels'] = $labels;
                    $json['total'] = $total;
                    $json['fecha'] = date('d/m/Y G:i:s');
                    $json['titulo'] = 'Votos Universidad Consejero Superior '.$nom_claustro;

                    //Formula dhont
                    $res = $this->dhont($labels, $total, $cant_cargos);
                    $json['data2'] = $res[1];
                    $json['columns2'] = $res[0];

                    $json['enviadas'] = $m_enviadas." enviadas de ".$m_total;
                    $json['confirmadas'] = $m_confirmadas." confirmadas de ".$m_total;

                    $data = array();
                    $labels = array();
                    $total = array();

                    $string_json = json_encode($json);
                    $nom_archivo = 'e'.str_replace('-','',$fecha).'/CS_TODO_'.strtoupper($nom_claustro[0]).'.json';
                    file_put_contents('resultados_json/'.$nom_archivo , $string_json);

                    $nom_claustro = $un_registro['claustro'];                
                }elseif($nom_claustro == null)
                    $nom_claustro = $un_registro['claustro'];

                $r['lista'] = utf8_encode($un_registro['lista']);
                $r['sigla_lista'] = $un_registro['sigla_lista'];
                $r['ponderado'] = $un_registro['ponderado'];

                $labels[] = $un_registro['sigla_lista'];
                $total[] = $un_registro['ponderado'];
                $cant_cargos = $un_registro['cant_cargos'];

                //Datos de mesas
                $m_enviadas = $un_registro['m_enviadas'];
                $m_confirmadas = $un_registro['m_confirmadas'];
                $m_total = $un_registro['m_total'];

                $data[] = $r;
            }

            if(isset($data) && $nom_claustro != null){//Quedo un ultimo claustro sin guardar
                $json = array();
                $json['columns'] = $columns;
                $json['data'] = $data;
                $json['labels'] = $labels;
                $json['total'] = $total;

                //Formula dhont
                $res = $this->dhont($labels, $total, $cant_cargos);
                $json['data2'] = $res[1];
                $json['columns2'] = $res[0];

                $json['fecha'] = date('d/m/Y G:i:s');
                $json['titulo'] = 'Votos Universidad Consejero Superior '.$nom_claustro;

                $json['enviadas'] = $m_enviadas." enviadas de ".$m_total;
                $json['confirmadas'] = $m_confirmadas." confirmadas de ".$m_total;

                $string_json = json_encode($json);

                $nom_archivo = 'e'.str_replace('-','',$fecha).'/CS_TODO_'.strtoupper($nom_claustro[0]);
                file_put_contents('resultados_json/'.$nom_archivo . '.json', $string_json);
            }
        }

        function dhont($listas, $valores, $escanos) {
            /*
             * Se multiplicarán por diez (10.000) los votos ponderados obtenidos por cada lista y se los
              dividirá desde uno (1) y hasta el total de cargos a ocupar.
              Luego, se agruparán en forma decreciente tantos cocientes como cargos a 
             * ocupar, sin considerar en que Listas se han obtenido. De esta manera 
             * se establecerá el "número repartidor", que es el menor de los cocientes 
             * citados.
              A continuación se dividirá la cantidad de votos lograda por cada Lista por 
             * el "número repartidor". El resultado obtenido dará el número de cargos 
             * que se adjudicará a cada una de ellas.

             */
            $datos = array();
            if (count($listas) > 0 && count($listas) == count($valores)) {
                $cocientes = array();
                for ($index1 = 0; $index1 < count($valores); $index1++) {
                    $valores[$index1] = $valores[$index1] * 10000;
                }
                foreach ($valores as $value) {
                    for ($index = 1; $index <= $escanos; $index++) {
                        $cocientes[] = $value / $index;
                    }
                }
                sort($cocientes);
                //print_r($cocientes);
                $repartidor = $cocientes[count($cocientes) - $escanos];

                $datos = array();
                foreach ($listas as $key => $lista) {
                    $fila = array('lista' => $lista,
                        'escanos' => floor($valores[$key] / $repartidor));
                    for ($index2 = 1; $index2 <= $escanos; $index2++) {
                        $fila[$index2] = floor($valores[$key] / $index2);
                        if ($index2 <= $fila['escanos']) {
                            if ($index2 > $escano_max)
                                $escano_max = $index2;
                            $orden = count($cocientes) - array_search($valores[$key] / $index2, $cocientes);
                            $fila[$index2] = "($orden)" . $fila[$index2];
                        }
                    }
                    $datos[] = $fila;
                }
            }
            $columns = array();
            $columns[] = array('field' => 'lista', 'title' => 'Listas');
            $columns[] = array('field' => 'escanos', 'title' => utf8_encode('Escaños'));
            for ($index3 = 1; $index3 <= $escano_max; $index3++) {
                $columns[] = array('field' => $index3, 'title' => $index3);
            }
            return array($columns, $datos);
        }

        //Metodo que calcula y genera archivos JSONS ubicados en /resultados_json/$fecha
        //con datos de resultados rector por cada claustro
        function datos_rector_claustro($fecha) {
            $sql = "
                select datos.*, m_enviadas, m_confirmadas, m_total
                from (
                    select claustro, t.id_claustro,
                        trim(lista) as lista, trim(sigla_lista) as sigla_lista, 
                        sum(ponderado) ponderado
                    from(
                        select votos_totales.id_tipo, votos_totales.id_nro_ue, 
                            votos_totales.sigla as sigla_ue, votos_totales.id_claustro, votos_totales.claustro, 
                            votos_totales.id_nro_lista, votos_totales.lista, votos_totales.sigla_lista, 
                            votos_totales.total, validos.validos, 
                            case when validos.validos <> 0 then 
                                    votos_totales.total/cast(validos.validos as decimal) * validos.mult 
                            end ponderado 
                        from (select a.id_tipo, ue.id_nro_ue, ue.sigla, 
                            m.id_claustro as id_claustro, c.descripcion claustro, l.id_nro_lista, l.nombre lista, 
                            l.sigla sigla_lista, sum(cant_votos) total 
                          from acta a 
                          inner join mesa m on m.id_mesa = a.de 
                          inner join sede s on s.id_sede = a.id_sede 
                          inner join unidad_electoral ue on ue.id_nro_ue = s.id_ue 
                          inner join claustro c on c.id = m.id_claustro 
                          inner join voto_lista_rector vl on vl.id_acta = a.id_acta 
                          inner join lista_rector l on l.id_nro_lista = vl.id_lista 
                            where m.estado > 1 and m.fecha = '".$fecha."' 
                            group by ue.id_nro_ue, ue.sigla, c.descripcion, l.nombre, l.id_nro_lista, l.sigla, s.id_ue, 
                                      m.id_claustro, a.id_tipo 
                            order by s.id_ue,m.id_claustro, l.nombre 
                    ) votos_totales
                    inner join (select id_ue, id_claustro, 
                            case ue.nivel when 2 then cargos_cdirectivo
                                    when 3 then cargos_cdiras
                            end as mult, 
                            sum(cant_votos) validos 
                                    from sede s 
                                    inner join acta a on a.id_sede = s.id_sede 
                                    inner join mesa m on m.id_mesa = a.de 
                                    inner join voto_lista_rector vl on vl.id_acta = a.id_acta 
                                    inner join claustro cl on cl.id = m.id_claustro
                                    inner join unidad_electoral ue on ue.id_nro_ue = s.id_ue
                                where m.estado > 1 and m.fecha = '".$fecha."' 
                                group by id_ue, id_claustro, cargos_csuperior, ue.nivel, 
                                cargos_cdirectivo, cargos_cdiras  
                    ) validos on validos.id_ue = votos_totales.id_nro_ue 
                                and validos.id_claustro = votos_totales.id_claustro
                    ) t
                    group by id_tipo, claustro, lista, sigla_lista, id_claustro
                    order by claustro, lista
                ) datos
                inner join (
                    select count(*) as m_enviadas, m.id_claustro from mesa m
                    where m.fecha = '$fecha' and m.estado>1
                    group by m.id_claustro) m on m.id_claustro = datos.id_claustro
                inner join (
                    select count(*) as m_confirmadas, m.id_claustro from mesa m
                    where m.fecha = '$fecha' and m.estado>2
                    group by m.id_claustro) m2 on m2.id_claustro = m.id_claustro
                inner join (
                    select count(*)  as m_total, m.id_claustro from mesa m
                    where m.fecha = '$fecha'
                    group by m.id_claustro) t on t.id_claustro = m.id_claustro
                        ";
            $datos = toba::db('gu_kena')->consultar($sql);

            $columns = array();
            $columns[] = array('field' => 'lista', 'title' => 'Listas');
            $columns[] = array('field' => 'sigla_lista', 'title' => 'Sigla Listas');
            $columns[] = array('field' => 'ponderado', 'title' => 'Ponderado');

            $nom_claustro = null;

            //Datos de mesas
            $m_enviadas = null;
            $m_confirmadas = null;
            $m_total = null;

            $data = array();
            foreach($datos as $un_registro){
                if($nom_claustro != null && $nom_claustro != $un_registro['claustro']){
                    $json = array();
                    $json['columns'] = $columns;
                    $json['data'] = $data;
                    $json['labels'] = $labels;
                    $json['total'] = $total;
                    $json['fecha'] = date('d/m/Y G:i:s');
                    $json['titulo'] = 'Votos Universidad Rector '.$nom_claustro;

                    $json['enviadas'] = $m_enviadas." enviadas de ".$m_total;
                    $json['confirmadas'] = $m_confirmadas." confirmadas de ".$m_total;

                    $data = array();
                    $labels = array();
                    $total = array();

                    $string_json = json_encode($json);
                    $nom_archivo = 'e'.str_replace('-','',$fecha).'/R_TODO_'.strtoupper($nom_claustro[0]).'.json';
                    file_put_contents('resultados_json/'.$nom_archivo , $string_json);

                    $nom_claustro = $un_registro['claustro'];                
                }elseif($nom_claustro == null)
                    $nom_claustro = $un_registro['claustro'];

                $r['lista'] = utf8_encode($un_registro['lista']);
                $r['sigla_lista'] = $un_registro['sigla_lista'];
                $r['ponderado'] = $un_registro['ponderado'];

                $labels[] = $un_registro['sigla_lista'];
                $total[] = $un_registro['ponderado'];

                //Datos de mesas
                $m_enviadas = $un_registro['m_enviadas'];
                $m_confirmadas = $un_registro['m_confirmadas'];
                $m_total = $un_registro['m_total'];

                $data[] = $r;
            }

            if(isset($data) && $nom_claustro != null){//Quedo un ultimo claustro sin guardar
                $json = array();
                $json['columns'] = $columns;
                $json['data'] = $data;
                $json['labels'] = $labels;
                $json['total'] = $total;
                $json['fecha'] = date('d/m/Y G:i:s');
                $json['titulo'] = 'Votos Universidad Rector '.$nom_claustro;

                $json['enviadas'] = $m_enviadas." enviadas de ".$m_total;
                $json['confirmadas'] = $m_confirmadas." confirmadas de ".$m_total;

                $string_json = json_encode($json);

                $nom_archivo = 'e'.str_replace('-','',$fecha).'/R_TODO_'.strtoupper($nom_claustro[0]);
                file_put_contents('resultados_json/'.$nom_archivo . '.json', $string_json);
            }
        }

        // Resultado general de rector con lista | ponderado
        function datos_rector($fecha) {
            $sql = "
                select datos.*, m_enviadas, m_confirmadas, m_total
                from(
                    select ue, id_nro_lista, trim(lista) as lista, 
                        trim(sigla_lista) as sigla_lista, sum(pond) as ponderado,
                        total_votos_blancos, total_votos_nulos, total_votos_recurridos
                    from (
                        select id_nro_lista, lista as Lista, sigla_lista, vl.ue, 
                        sum(cast(votos_lista as real)/votos_validos)*ponderacion pond,
                        sum(total_votos_blancos) total_votos_blancos, 
                        sum(total_votos_nulos) total_votos_nulos, sum(total_votos_recurridos) total_votos_recurridos
                        from(
                        select ue.sigla as ue,c.descripcion claustro, l.id_nro_lista, 
                        l.nombre as lista, l.sigla as sigla_lista,
                        sum(cant_votos) as votos_lista, a.total_votos_blancos, 
                        a.total_votos_nulos, a.total_votos_recurridos
                        from acta a inner join voto_lista_rector vl on a.id_acta=vl.id_acta and a.id_tipo=4
                                inner join lista_rector l on vl.id_lista=l.id_nro_lista
                                inner join mesa m on a.de=m.id_mesa
                                inner join claustro c on m.id_claustro=c.id
                                inner join sede s on a.id_sede=s.id_sede
                                inner join unidad_electoral ue on s.id_ue=ue.id_nro_ue
                        where m.fecha = '$fecha' and m.estado > 1
                        group by ue,claustro, lista, l.id_nro_lista, a.total_votos_blancos, 
                        a.total_votos_nulos, a.total_votos_recurridos
                        )vl inner join 
                        (
                        select ue.sigla as ue,c.descripcion claustro, cargos_cdirectivo as ponderacion, sum(cant_votos) as votos_validos
                        from acta a inner join voto_lista_rector vl on a.id_acta=vl.id_acta
                                inner join lista_rector l on vl.id_lista=l.id_nro_lista
                                inner join mesa m on a.de=m.id_mesa
                                inner join claustro c on m.id_claustro=c.id
                                inner join sede s on a.id_sede=s.id_sede
                                inner join unidad_electoral ue on s.id_ue=ue.id_nro_ue
                        where m.fecha = '$fecha' and m.estado > 1
                        group by ue,claustro,ponderacion

                        )vv on vl.ue=vv.ue and vl.claustro=vv.claustro
                        group by lista,vl.ue,ponderacion, id_nro_lista, sigla_lista
                        order by vl.ue, lista
                    )a group by lista, ue, id_nro_lista, sigla_lista, 
                        total_votos_blancos, total_votos_nulos, total_votos_recurridos
                    order by ue, ponderado
                ) datos, 
                (select count(*) as m_enviadas from mesa m
                               where m.fecha = '$fecha' and m.estado>1) m,
                (select count(*) as m_confirmadas from mesa m
                            where m.fecha = '$fecha' and m.estado>2) m2,
                (select count(*) as m_total from mesa m
                            where m.fecha = '$fecha') t
                        ";
            $datos = toba::db('gu_kena')->consultar($sql);

            if(sizeof($datos) > 0){
                $nom_ue = null;
                $total = array();
                $data = array();
                $r = array();
                foreach($datos as $un_registro){
                    if($nom_ue == null){
                        $nom_ue = $un_registro['ue'];                
                    }elseif($nom_ue != $un_registro['ue']){
                        $r['ue'] = $nom_ue;
                        $data[] = $r;

                        $total['Blancos'] += $r['Blancos'];
                        $total['Nulos'] += $r['Nulos'];
                        $total['Recurridos'] += $r['Recurridos'];

                        $r = array();
                        $nom_ue = $un_registro['ue'];
                    }

                    $r[$un_registro['sigla_lista']] = $un_registro['ponderado'];
                    $r['Blancos'] = $un_registro['total_votos_blancos'];
                    $r['Nulos'] = $un_registro['total_votos_nulos'];
                    $r['Recurridos'] = $un_registro['total_votos_recurridos'];

                    $total[$un_registro['sigla_lista']] += $un_registro['ponderado'];
                }
                $r['ue'] = $nom_ue;
                $data[] = $r;

                $total['Blancos'] += $r['Blancos'];
                $total['Nulos'] += $r['Nulos'];
                $total['Recurridos'] += $r['Recurridos'];

                $columns = array();
                $columns[] = array('field' => 'ue', 'title' => 'Unidad Electoral');
                foreach($total as $key => $value){
                     $columns[] = array('field' => $key, 'title' => $key);
                }

                $labels = array();
                $totales = array();
                foreach($total as $key => $value){
                    $labels[] = $key;
                    $totales[] = $value;
                }

               //Armado del json
                $json = array();
                $total['ue'] = 'TOTAL';
                $data[] = $total;
                $json['data'] = $data;
                $json['columns'] = $columns;

                $json['labels'] = $labels;
                $json['total'] = $totales;
                $json['fecha'] = date('d/m/Y G:i:s');
                $json['titulo'] = 'Votos Universidad Rector';

                $json['enviadas'] = $datos[0]['m_enviadas']." enviadas de ".$datos[0]['m_total'];
                $json['confirmadas'] = $datos[0]['m_confirmadas']." confirmadas de ".$datos[0]['m_total'];

                $string_json = json_encode($json);

                $nom_archivo = 'e'.str_replace('-','',$fecha).'/R_TODO_T';
                file_put_contents('resultados_json/'.$nom_archivo . '.json', $string_json);
            }
        }
}

new cron();
?>