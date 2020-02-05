drop table IF EXISTS node_tree_names;
drop table IF EXISTS node_tree;


CREATE TABLE node_tree
(
  idNode int PRIMARY KEY,
  level int,
  iLeft int,
  iRight int
);

CREATE TABLE node_tree_names
(
  idNode int,
  language ENUM('english', 'italian'),
  nodeName VARCHAR(256)
);

ALTER TABLE node_tree_names ADD FOREIGN KEY (idNode) REFERENCES node_tree (idNode);


-- end of filevangelion