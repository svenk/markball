# Markball: Proposal for a simple human-readable archiving file format

This is a proposal for a simple format for embedding text files in text files
as *fenced blocks* while maintaining readability. This is archived by applying
the *markup* paradigm. Machine-readability is archieved with a *line by line* approach.

The file format follows closely the idea of *Markdown* (compiling to HTML)
and markup languages in general, while having an emphasis on embedding other
files similar to a *tarball*. Therefore, I call it *Markball*.

## Purpose

The language was invented to embed log files and long textual information into
command line generated reports created by a build system. Therefore, the intend was
to create both human-readable as well as machine-readable files which can be easily
postprocessed to create rich web pages.

## Definition

We introduce the basic atom as a single line in the format
```
#{label} content
```
and refer to this as a *tag* or *command* named `label` with payload `content`.
Both `label` and `content` can be any strings, limited by the occurance of
the closing `}` or the newline. We suggest to treat the label not case sensitive.
All lines in the file not starting with the regexp. `#{([^}]+)}` are treated as
ordinary text.

Commands may influcence the following lines but not the preceding ones. As only
special commands so far, we define `begin` and `end` as defining *fenced blocks*.
The block structure always has to follow
```
#{begin} foo bar baz
text
#{end} foo bar baz
```
Here, the single line `content` serves as unique identifier for the block, and
`begin` and `end` identifiers have to match.

As the first line of the file, we suggest to write
```
#{markball/1.0} any description of the file
```
where `markball/1.0` defines the file format `1.0` and serves as a magic number.


## Minimal example

```
#{HEAD} An example file
This is just plain text, describing what is inside this file.

We embed here:

#{BEGIN} another file or input
This is the content of another file.
#{END} another file or input

That's all of it. As this is markup, you may come up with your own tags
such as

#{ITEM} an ordered
#{ITEM} or unordered
#{ITEM} list

or anything else.
```

This example easily translates to a markup language such as XML or HTML. A
possible translation *could* be:

```
<head>An example file</head>
<p>This is just plain text, describing what is inside this file.

<p>We embed here:

<section title="another file or input">
This is the content of another file.
</section>

<p>That's all of it. As this is markup, you may come up with your own tags
such as

<ul>
<li> an ordered
<li> or unordered
<li> list
</ul>

<p>or anything else.
```

Obviously, there has been made some decisions here -- note the different
treatment of the `head` tag vs. the `item` tag as well as the introduction
of paragraphs similar to the definition in markdown.

## Embedding of files

Obviously, embedding files which are *not* plain-text ASCII is a problem,
as in any other widespread plain text markup file. Such files either have to
be binary represented (i.e. in BASE64).

In any case, representing text files inside a markball can again lead to
problems, think of embedding a markball inside a markball. Therefore, we
suggest to introduce random or even unique *nonce* in the labels of a
section. We also suggest to force users to close a `BEGIN x` with a `END x`
simliar to the usage of a *TeX environment*. Here is an example:

```
#{BEGIN} label {unique nonce}
#{foo} this could be another markball file.
#{BEGIN} bar
#{END} bar
#{END} label {unique nonce}
```

Note that we do not prescribe whether block labels have to follow a form, they
can be anything until the end of the line.

## Parsing

Due to the explicit line-wise structure of this file, parsing in any programming
language should be easy. The choice of the structure `#{foo}` in the beginning
of a line allows to process the outcome afterwards as regular markdown (with
headings or numbered lists). The line-wise structure also allows easy creation
of these files in any language, especially from the shell (think of Makefiles
and similiar).

## Transformations

As already indicated, the transformation of a *markball* file into other file
formats are straightforward, for instance

  - one can come up with an 1:1 mapping to HTML
  - one can remove structure and come up with a stripped down markdown file
    with regular fenced code blocks
  - one can come up with a translation to a real tarball (or any other archive
    format such as *zip*) where all fenced blocks are put into regular files,
    using the identifier as file name.

## Copyright: Public Domain

This format was proposed by SvenK on 2011-11-06 and released in the public domain.
